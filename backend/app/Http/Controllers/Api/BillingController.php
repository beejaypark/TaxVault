<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    private const STRIPE_URL = 'https://api.stripe.com/v1';

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $price = null;
        if (config('services.stripe.secret') && config('services.stripe.price_id')) {
            try {
                $stripePrice = $this->stripe('get', '/prices/'.config('services.stripe.price_id'), []);
                $price = [
                    'amount' => $stripePrice['unit_amount'] ?? null,
                    'currency' => strtoupper((string) ($stripePrice['currency'] ?? 'AUD')),
                    'interval' => $stripePrice['recurring']['interval'] ?? null,
                    'interval_count' => $stripePrice['recurring']['interval_count'] ?? 1,
                ];
            } catch (\Throwable $exception) {
                Log::warning('Unable to load Stripe price.', ['error' => $exception->getMessage()]);
            }
        }

        return response()->json(['data' => [
            'status' => $user->stripe_subscription_status,
            'trial_ends_at' => $user->trial_ends_at?->toISOString(),
            'subscription_ends_at' => $user->subscription_ends_at?->toISOString(),
            'active' => in_array($user->stripe_subscription_status, [
                'trialing', 'active', 'past_due',
            ], true),
            'price' => $price,
        ]]);
    }

    public function checkout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plan = $request->string('plan')->toString() ?: 'monthly';
        $trial = $request->boolean('trial', true);
        $this->ensureConfigured();

        $priceId = $plan === 'yearly'
            ? config('services.stripe.yearly_price_id')
            : config('services.stripe.monthly_price_id');
        abort_unless(is_string($priceId) && $priceId !== '', 503, 'The selected Stripe plan is not configured.');

        $customerId = $user->stripe_customer_id;
        if (! is_string($customerId) || $customerId === '') {
            $customer = $this->stripe('post', '/customers', [
                'email' => $user->email,
                'name' => $user->display_name,
                'metadata[taxvault_user_id]' => $user->getKey(),
            ]);
            $customerId = (string) $customer['id'];
            $user->stripe_customer_id = $customerId;
            $user->save();
        }

        $url = rtrim((string) config('services.stripe.public_url'), '/');
        $checkoutData = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'subscription_data[metadata][taxvault_user_id]' => $user->getKey(),
            'success_url' => $url.'/settings?billing=success',
            'cancel_url' => $url.'/settings?billing=cancelled',
            'client_reference_id' => $user->getKey(),
        ];
        if ($trial) {
            $checkoutData['subscription_data[trial_period_days]'] = 30;
        }

        $session = $this->stripe('post', '/checkout/sessions', $checkoutData);

        return response()->json(['data' => ['url' => $session['url']]]);
    }

    public function portal(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureConfigured();

        abort_unless(is_string($user->stripe_customer_id) && $user->stripe_customer_id !== '', 422, 'No Stripe customer is linked.');

        $url = rtrim((string) config('services.stripe.public_url'), '/');
        $session = $this->stripe('post', '/billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => $url.'/settings',
        ]);

        return response()->json(['data' => ['url' => $session['url']]]);
    }

    public function cancel(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureConfigured();
        abort_unless(is_string($user->stripe_subscription_id) && $user->stripe_subscription_id !== '', 422, 'No active subscription is linked.');

        $subscription = $this->stripe('post', '/subscriptions/'.rawurlencode($user->stripe_subscription_id), [
            'cancel_at_period_end' => 'true',
        ]);
        $user->stripe_subscription_status = $subscription['status'] ?? $user->stripe_subscription_status;
        $user->subscription_ends_at = $this->timestamp($subscription['current_period_end'] ?? null);
        $user->save();

        return response()->json(['data' => [
            'status' => $user->stripe_subscription_status,
            'subscription_ends_at' => $user->subscription_ends_at?->toISOString(),
            'cancel_at_period_end' => (bool) ($subscription['cancel_at_period_end'] ?? true),
        ]]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $signature = (string) $request->header('Stripe-Signature');
        if ($secret === '' || ! $this->validSignature($request->getContent(), $signature, $secret)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event = $request->json()->all();
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        try {
            match ($type) {
                'checkout.session.completed' => $this->checkoutCompleted($object),
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted' => $this->subscriptionUpdated($object),
                default => null,
            };
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook processing failed.', [
                'event_type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function checkoutCompleted(array $session): void
    {
        $user = User::query()->find($session['client_reference_id'] ?? null);
        if (! $user) {
            return;
        }

        $user->stripe_customer_id = $session['customer'] ?? $user->stripe_customer_id;
        $user->stripe_subscription_id = $session['subscription'] ?? $user->stripe_subscription_id;
        $user->save();
    }

    private function subscriptionUpdated(array $subscription): void
    {
        $userId = $subscription['metadata']['taxvault_user_id'] ?? null;
        $user = $userId
            ? User::query()->find($userId)
            : User::query()->where('stripe_customer_id', $subscription['customer'] ?? null)->first();
        if (! $user) {
            return;
        }

        $user->stripe_customer_id = $subscription['customer'] ?? $user->stripe_customer_id;
        $user->stripe_subscription_id = $subscription['id'] ?? $user->stripe_subscription_id;
        $user->stripe_subscription_status = $subscription['status'] ?? null;
        $user->trial_ends_at = $this->timestamp($subscription['trial_end'] ?? null);
        $user->subscription_ends_at = $this->timestamp($subscription['cancel_at'] ?? $subscription['ended_at'] ?? null);
        $user->save();
    }

    private function stripe(string $method, string $path, array $data): array
    {
        $client = Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '');
        $response = strtolower($method) === 'get'
            ? $client->get(self::STRIPE_URL.$path, $data)
            : $client->post(self::STRIPE_URL.$path, $data);

        if ($response->failed()) {
            Log::error('Stripe API request failed.', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);
        }

        $response->throw();

        return $response->json();
    }

    private function ensureConfigured(): void
    {
        abort_unless(
            is_string(config('services.stripe.secret'))
                && config('services.stripe.secret') !== ''
                && is_string(config('services.stripe.price_id'))
                && config('services.stripe.price_id') !== '',
            503,
            'Stripe billing is not configured.',
        );
    }

    private function validSignature(string $payload, string $header, string $secret): bool
    {
        $parts = collect(explode(',', $header))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            return [$key => $value];
        });
        $timestamp = (int) $parts->get('t', 0);
        $signature = (string) $parts->get('v1', '');
        if ($timestamp <= 0 || $signature === '' || abs(time() - $timestamp) > 300) {
            return false;
        }

        return hash_equals($signature, hash_hmac('sha256', $timestamp.'.'.$payload, $secret));
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) && (int) $value > 0
            ? CarbonImmutable::createFromTimestamp((int) $value)
            : null;
    }
}

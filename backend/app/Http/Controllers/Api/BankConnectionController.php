<?php

namespace App\Http\Controllers\Api;

use App\Application\Transactions\ImportTransactions;
use App\Domain\Banking\BasiqClient;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BankConnectionController extends Controller
{
    private const PROVIDER = 'basiq';

    public function status(Request $request, BasiqClient $basiq): JsonResponse
    {
        $connection = $this->connection($request);

        return response()->json(['data' => [
            'enabled' => $basiq->enabled(),
            'connected' => $connection !== null,
            'last_synced_at' => $connection?->last_synced_at?->toIso8601String(),
        ]]);
    }

    public function connect(Request $request, BasiqClient $basiq): JsonResponse
    {
        if (! $basiq->enabled()) {
            return $this->unavailable();
        }

        $user = $request->user();

        try {
            $connection = $this->connection($request) ?? BankConnection::query()->create([
                'user_id' => $user->getKey(),
                'provider' => self::PROVIDER,
                'provider_user_id' => $basiq->createUser($user->email),
            ]);

            return response()->json(['data' => ['url' => $basiq->consentUrl($connection->provider_user_id)]]);
        } catch (RuntimeException $e) {
            report($e);

            return $this->failed();
        }
    }

    public function sync(Request $request, BasiqClient $basiq, ImportTransactions $importTransactions): JsonResponse
    {
        $connection = $this->connection($request);

        if (! $basiq->enabled() || $connection === null) {
            return $this->unavailable();
        }

        $user = $request->user();
        $since = FinancialYear::query()->where('user_id', $user->getKey())->min('start_date');

        if ($since === null) {
            return response()->json(['data' => ['imported' => 0, 'duplicates' => 0, 'outside_financial_years' => 0]]);
        }

        try {
            $transactions = $basiq->transactions($connection->provider_user_id, substr((string) $since, 0, 10));
        } catch (RuntimeException $e) {
            report($e);

            return $this->failed();
        }

        $rows = [];
        $keys = [];
        foreach ($transactions as $index => $transaction) {
            $rows[$index] = ['date' => $transaction['date'], 'description' => $transaction['description'], 'amount' => $transaction['amount']];
            $keys[$index] = 'basiq:'.$transaction['id'];
        }

        $result = $importTransactions->execute($user, $rows, $keys, 'bank_feed');
        $connection->update(['last_synced_at' => now()]);

        return response()->json(['data' => $result + ['last_synced_at' => $connection->last_synced_at?->toIso8601String()]]);
    }

    private function connection(Request $request): ?BankConnection
    {
        return BankConnection::query()
            ->where('user_id', $request->user()->getKey())
            ->where('provider', self::PROVIDER)
            ->first();
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Bank connection is not available.'], 404);
    }

    private function failed(): JsonResponse
    {
        return response()->json(['message' => 'The bank provider could not be reached. Try again shortly.'], 502);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\AccountantGrant;
use App\Support\ViewerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accountants authenticate as themselves but read the data of the owner who
 * invited them. From here on `$request->user()` is the owner, so existing
 * controllers need no changes; the real person stays in the `actor`
 * request attribute for the audit log. Accountants are limited to the
 * read-only routes their grant allows.
 */
class ActAsGrantedOwner
{
    public function __construct(private readonly ViewerContext $viewer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAccountant()) {
            return $next($request);
        }

        $grants = AccountantGrant::query()
            ->with('owner')
            ->where('accountant_user_id', $user->getKey())
            ->where('status', 'active')
            ->get();

        $requested = $request->header('X-TaxVault-Client');
        $grant = $requested !== null
            ? $grants->firstWhere('owner_id', $requested)
            : $grants->first();

        if ($grant === null || $grant->owner === null || $grant->owner->status !== 'active') {
            return $this->deny('No active access has been granted to you.');
        }

        if (! $this->isAllowed($request, $grant)) {
            return $this->deny('Accountant access is read-only and limited to the sections you were granted.');
        }

        $this->viewer->restrictFinancialYears($grant->financial_year_ids);
        $request->attributes->set('actor', $user);
        $request->attributes->set('grant', $grant);
        $request->setUserResolver(fn () => $grant->owner);

        return $next($request);
    }

    private function isAllowed(Request $request, AccountantGrant $grant): bool
    {
        $path = trim(preg_replace('#^api/#', '', $request->path()), '/');
        $segment = explode('/', $path)[0];

        if ($request->isMethod('POST') && $path === 'exports') {
            return $grant->allows('transactions');
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if (preg_match('#^financial-years/[^/]+/return-summary$#', $path)) {
            return $grant->allows('transactions');
        }
        return match ($segment) {
            'financial-years', 'document-types' => true,
            'documents' => $grant->allows('documents'),
            'transactions', 'exports' => $grant->allows('transactions'),
            'reviews' => $grant->allows('reviews'),
            'properties', 'investments' => $grant->allows('assets'),
            default => false,
        };
    }

    private function deny(string $message): Response
    {
        return response()->json(['message' => $message], 403);
    }
}

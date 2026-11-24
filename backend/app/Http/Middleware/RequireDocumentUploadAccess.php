<?php

namespace App\Http\Middleware;

use App\Domain\Billing\DocumentUploadAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireDocumentUploadAccess
{
    public function __construct(private readonly DocumentUploadAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $this->access->allows($request->user()), 402, 'Your free trial has ended. Choose a monthly or yearly plan to continue uploading documents.');

        return $next($request);
    }
}

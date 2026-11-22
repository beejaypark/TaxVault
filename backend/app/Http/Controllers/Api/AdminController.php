<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotificationLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $now = now();
        $customers = fn ($query) => $query->where(function ($query): void {
            $query->whereNull('role')->orWhere('role', '!=', 'accountant');
        });

        return response()->json(['data' => [
            'total_users' => $customers(User::query())->count(),
            'new_users_7_days' => User::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'new_users_30_days' => User::query()->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'recent_users' => $customers(User::query())->latest()->limit(20)->get([
                'id', 'email', 'display_name', 'identity_provider', 'status', 'created_at',
            ]),
            'notification_logs' => AdminNotificationLog::query()->latest('created_at')->limit(20)->get(),
        ]]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Notifications\Services\EmailNotificationSender;
use App\Domain\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    /** Upcoming lodgement dates (past ones are hidden) with what is still open for each. */
    public function index(Request $request, EmailNotificationSender $sender): JsonResponse
    {
        $user = $request->user();
        $userId = $user->getKey();
        $today = CarbonImmutable::now(config('notifications.timezone'))->startOfDay();
        $years = FinancialYear::query()->where('user_id', $userId)->get()->keyBy('year_code');

        $items = [];
        foreach ($sender->deadlines($user) as $label => $deadline) {
            $daysLeft = (int) $today->diffInDays($deadline, false);

            if ($daysLeft < 0) {
                continue;
            }

            $year = str_starts_with($label, 'FY ') ? $years->get(substr($label, 3)) : null;
            $yearId = $year?->id;
            $yearEnd = $year ? CarbonImmutable::parse($year->end_date)->startOfDay() : null;
            $scope = fn ($query) => $yearId ? $query->where('financial_year_id', $yearId) : $query;

            $items[] = [
                'label' => $label === 'chosen' ? 'Your' : $label,
                'date' => $deadline->toDateString(),
                'days_left' => $daysLeft,
                'financial_year_end' => $yearEnd?->toDateString(),
                'days_to_year_end' => $yearEnd ? max(0, (int) $today->diffInDays($yearEnd, false)) : null,
                'completed' => $sender->isCompleted($user, $deadline),
                'financial_year_id' => $yearId,
                'uncategorised_transactions' => $scope(Transaction::query()->where('user_id', $userId)->whereNull('ato_category'))->count(),
                'documents_to_review' => $scope(Document::query()->where('user_id', $userId)->whereIn('status', EmailNotificationSender::ATTENTION_STATUSES))->count(),
            ];
        }

        usort($items, fn ($a, $b) => $a['date'] <=> $b['date']);

        return response()->json(['data' => $items]);
    }

    public function complete(Request $request, string $date): JsonResponse
    {
        return $this->mark($request, $date, true);
    }

    public function reopen(Request $request, string $date): JsonResponse
    {
        return $this->mark($request, $date, false);
    }

    private function mark(Request $request, string $date, bool $done): JsonResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        $user = $request->user();
        $current = collect($user->preferences['completedDeadlines'] ?? []);
        $next = ($done ? $current->push($date) : $current->reject(fn ($d) => $d === $date))->unique()->values()->all();

        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], ['completedDeadlines' => $next])])->save();

        return response()->json(['data' => ['completedDeadlines' => $next]]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Investments\Models\Investment;
use App\Domain\Properties\Models\Property;
use App\Domain\TaxNews\Models\TaxNews;
use App\Domain\TaxNews\Services\TaxNewsAiRecommender;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TaxNewsController extends Controller
{
    public function sync(): JsonResponse
    {
        Artisan::call('tax-news:sync');

        return response()->json([
            'message' => 'Tax news sync completed.',
            'output' => trim(Artisan::output()),
        ]);
    }

    public function index(Request $request, TaxNewsAiRecommender $aiRecommender): JsonResponse
    {
        $query = TaxNews::query()
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '>=', now()->subDays(180));
            })
            ->orderByDesc('published_at')
            ->limit(100);

        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all' && ! $request->boolean('recommended')) {
            $query->where('category', $category);
        }

        $news = $request->boolean('recommended')
            ? $this->recommended($request)
            : $query->get()->all();

        if ($category !== '' && $category !== 'all' && $request->boolean('recommended')) {
            $news = array_values(array_filter($news, fn (TaxNews $item): bool => $item->category === $category));
        }

        if ($request->boolean('ai')) {
            $ai = $aiRecommender->enhance($news, $this->profileTags($request), $request->user()->ai_settings);

            return response()->json(['data' => collect($news)->map(function (TaxNews $item) use ($ai): array {
                $data = $item->toArray();
                $data['ai_summary'] = $ai[$item->getKey()]['summary'] ?? null;
                $data['ai_reason'] = $ai[$item->getKey()]['reason'] ?? null;

                return $data;
            })->values()]);
        }

        return response()->json(['data' => $news]);
    }

    /** @return list<TaxNews> */
    private function recommended(Request $request): array
    {
        $userId = $request->user()->getKey();
        $hasProperty = Property::query()->where('user_id', $userId)->exists();
        $investmentTypes = Investment::query()
            ->where('user_id', $userId)
            ->whereNull('disposal_date')
            ->pluck('investment_type')
            ->map(fn (string $type): string => strtolower($type))
            ->all();

        $news = TaxNews::query()
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '>=', now()->subDays(180));
            })
            ->get()
            ->map(function (TaxNews $item) use ($hasProperty, $investmentTypes): array {
                $text = strtolower($item->title.' '.($item->summary ?? '').' '.$item->category);
                $score = $item->importance === 'important' ? 1 : 0;

                if ($hasProperty && ($item->category === 'rental' || $this->containsAny($text, ['rental', 'property', 'landlord', 'depreciation', 'capital gains']))) {
                    $score += 4;
                }

                foreach ($investmentTypes as $type) {
                    if ($this->containsAny($type, ['super', 'superannuation']) && ($item->category === 'super' || $this->containsAny($text, ['super', 'superannuation']))) {
                        $score += 4;
                    }
                    if ($this->containsAny($type, ['share', 'stock', 'equity', 'managed fund', 'etf']) && $this->containsAny($text, ['share', 'dividend', 'capital gains', 'managed fund', 'etf', 'investment'])) {
                        $score += 3;
                    }
                }

                if ($score === 0 && ($hasProperty || $investmentTypes !== [])) {
                    $score = 1;
                }

                return ['item' => $item, 'score' => $score];
            })
            ->sort(function (array $left, array $right): int {
                return $right['score'] <=> $left['score']
                    ?: (($right['item']->published_at?->getTimestamp() ?? 0) <=> ($left['item']->published_at?->getTimestamp() ?? 0));
            })
            ->take(6)
            ->pluck('item')
            ->values()
            ->all();

        return $news;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function profileTags(Request $request): array
    {
        $userId = $request->user()->getKey();
        $tags = [];

        if (Property::query()->where('user_id', $userId)->exists()) {
            $tags[] = 'property owner';
            $tags[] = 'rental property tax';
        }

        Investment::query()
            ->where('user_id', $userId)
            ->whereNull('disposal_date')
            ->pluck('investment_type')
            ->each(function (string $type) use (&$tags): void {
                $normalised = strtolower($type);
                if ($this->containsAny($normalised, ['super', 'superannuation'])) {
                    $tags[] = 'superannuation';
                }
                if ($this->containsAny($normalised, ['share', 'stock', 'equity', 'managed fund', 'etf'])) {
                    $tags[] = 'shares and investments';
                }
                if ($this->containsAny($normalised, ['property', 'real estate'])) {
                    $tags[] = 'property investment';
                }
            });

        return array_values(array_unique($tags));
    }
}

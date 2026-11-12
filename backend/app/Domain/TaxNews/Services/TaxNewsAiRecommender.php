<?php

namespace App\Domain\TaxNews\Services;

use App\Domain\TaxNews\Models\TaxNews;
use Illuminate\Support\Facades\Http;

final class TaxNewsAiRecommender
{
    /** @param list<TaxNews> $news @param list<string> $profileTags @return array<string, array{summary: string, reason: string}> */
    public function enhance(array $news, array $profileTags, ?array $userSettings = null): array
    {
        $enabled = $userSettings === null
            ? true
            : (bool) ($userSettings['enabled'] ?? true);
        $apiKey = (string) ($userSettings['api_key'] ?? config('services.openai.api_key'));
        if ($apiKey === '' || $news === []) {
            return [];
        }
        if (!$enabled) {
            return [];
        }

        $items = array_map(static fn (TaxNews $item): array => [
            'id' => (string) $item->getKey(),
            'title' => $item->title,
            'summary' => $item->summary,
            'category' => $item->category,
            'source' => $item->source,
        ], $news);
        $provider = $userSettings['provider'] ?? 'openai';
        if ($provider === 'anthropic') {
            return $this->callAnthropic($items, $profileTags, $userSettings);
        }
        if ($provider === 'gemini') {
            return $this->callGemini($items, $profileTags, $userSettings);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                    'model' => $userSettings['model'] ?? config('services.openai.tax_news_model', 'gpt-4o-mini'),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => 'You are an Australian tax news assistant. Summarise only the supplied official news. Do not provide tax advice. Keep each summary under 28 words and explain relevance cautiously.',
                            ]],
                        ],
                        [
                            'role' => 'user',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => json_encode([
                                    'profile_tags' => $profileTags,
                                    'news' => $items,
                                ], JSON_THROW_ON_ERROR),
                            ]],
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'tax_news_recommendations',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'items' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string'],
                                                'summary' => ['type' => 'string'],
                                                'reason' => ['type' => 'string'],
                                            ],
                                            'required' => ['id', 'summary', 'reason'],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                ],
                                'required' => ['items'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json();

            $text = $response['output_text'] ?? data_get($response, 'output.0.content.0.text');
            $decoded = is_string($text) ? json_decode($text, true, 512, JSON_THROW_ON_ERROR) : null;

            return collect($decoded['items'] ?? [])
                ->filter(fn ($item): bool => is_array($item) && isset($item['id'], $item['summary'], $item['reason']))
                ->mapWithKeys(fn (array $item): array => [(string) $item['id'] => [
                    'summary' => (string) $item['summary'],
                    'reason' => (string) $item['reason'],
                ]])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<array<string, mixed>> $items @param list<string> $profileTags @return array<string, array{summary: string, reason: string}> */
    private function callAnthropic(array $items, array $profileTags, array $settings): array
    {
        $apiKey = (string) ($settings['api_key'] ?? config('services.anthropic.api_key'));
        if ($apiKey === '') return [];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->acceptJson()->timeout(20)->post(rtrim((string) config('services.anthropic.base_url'), '/').'/messages', [
                'model' => $settings['model'] ?? config('services.anthropic.tax_news_model'),
                'max_tokens' => 1200,
                'system' => 'You are an Australian tax news assistant. Return JSON only with an items array. Summarise only supplied official news, under 28 words per summary, and do not provide tax advice.',
                'messages' => [[
                    'role' => 'user',
                    'content' => json_encode(['profile_tags' => $profileTags, 'news' => $items], JSON_THROW_ON_ERROR),
                ]],
            ])->throw()->json();

            return $this->parse(data_get($response, 'content.0.text'));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<array<string, mixed>> $items @param list<string> $profileTags @return array<string, array{summary: string, reason: string}> */
    private function callGemini(array $items, array $profileTags, array $settings): array
    {
        $apiKey = (string) ($settings['api_key'] ?? config('services.gemini.api_key'));
        if ($apiKey === '') return [];

        try {
            $model = $settings['model'] ?? config('services.gemini.tax_news_model');
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->timeout(20)
                ->post(rtrim((string) config('services.gemini.base_url'), '/').'/models/'.$model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => 'You are an Australian tax news assistant. Return JSON only with an items array. Summarise only supplied official news, under 28 words per summary, and do not provide tax advice.']]],
                    'contents' => [['parts' => [['text' => json_encode(['profile_tags' => $profileTags, 'news' => $items], JSON_THROW_ON_ERROR)]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json'],
                ])->throw()->json();

            return $this->parse(data_get($response, 'candidates.0.content.parts.0.text'));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, array{summary: string, reason: string}> */
    private function parse(mixed $text): array
    {
        if (!is_string($text) || $text === '') return [];
        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            return collect($decoded['items'] ?? [])
                ->filter(fn ($item): bool => is_array($item) && isset($item['id'], $item['summary'], $item['reason']))
                ->mapWithKeys(fn (array $item): array => [(string) $item['id'] => [
                    'summary' => (string) $item['summary'],
                    'reason' => (string) $item['reason'],
                ]])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}

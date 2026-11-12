<?php

namespace App\Console\Commands;

use App\Domain\TaxNews\Models\TaxNews;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class SyncTaxNews extends Command
{
    protected $signature = 'tax-news:sync';
    protected $description = 'Sync official Australian tax news feeds';

    public function handle(): int
    {
        $saved = 0;

        foreach (config('tax-news.feeds', []) as $feed) {
            if (empty($feed['url'])) {
                continue;
            }

            try {
                $xml = Http::timeout(20)->retry(2, 250)->get($feed['url'])->throw()->body();
                $items = $this->items(new SimpleXMLElement($xml));
            } catch (\Throwable $exception) {
                $this->warn($feed['source'].': '.$exception->getMessage());
                continue;
            }

            foreach ($items as $item) {
                $externalId = $item['id'] ?: $item['url'];
                if ($externalId === '' || $item['title'] === '' || $item['url'] === '') {
                    continue;
                }

                TaxNews::query()->updateOrCreate(
                    ['source' => $feed['source'], 'external_id' => mb_substr($externalId, 0, 500)],
                    [
                        'title' => mb_substr($item['title'], 0, 500),
                        'summary' => $item['summary'] !== '' ? mb_substr($item['summary'], 0, 2000) : null,
                        'url' => $item['url'],
                        'category' => $this->category($item['title'].' '.$item['summary']),
                        'importance' => $this->importance($item['title'].' '.$item['summary']),
                        'status' => $this->status($item['title'].' '.$item['summary']),
                        'published_at' => $item['published_at'],
                    ],
                );
                $saved++;
            }
        }

        $this->info("Synced {$saved} tax news items.");

        return self::SUCCESS;
    }

    /** @return list<array{id: string, title: string, summary: string, url: string, published_at: ?CarbonImmutable}> */
    private function items(SimpleXMLElement $xml): array
    {
        $items = [];
        $nodes = isset($xml->channel->item) ? $xml->channel->item : $xml->entry;

        foreach ($nodes as $node) {
            $namespaces = $node->getName() === 'entry' ? $node->children('http://www.w3.org/2005/Atom') : null;
            $link = $node->link['href'] ?? $node->link;
            $published = (string) ($node->pubDate ?? $node->published ?? $node->updated ?? '');
            $summary = (string) ($node->description ?? $node->summary ?? $node->content ?? '');
            $items[] = [
                'id' => trim((string) ($node->guid ?? $node->id ?? $link)),
                'title' => trim((string) ($node->title ?? '')),
                'summary' => trim(preg_replace('/\\s+/', ' ', strip_tags($summary)) ?? ''),
                'url' => trim((string) $link),
                'published_at' => $published !== '' ? CarbonImmutable::parse($published) : null,
            ];
        }

        return $items;
    }

    private function category(string $text): string
    {
        $text = strtolower($text);
        foreach ([
            'rental' => ['rental', 'property', 'landlord'],
            'super' => ['super', 'superannuation'],
            'small_business' => ['small business', 'business', 'gst', 'fb t', 'fbt'],
            'individuals' => ['individual', 'income tax', 'deduction', 'work-related'],
        ] as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    private function importance(string $text): string
    {
        $text = strtolower($text);

        return preg_match('/\\b(urgent|important|must|deadline|effective|law|legislation|penalt|warning|alert)\\b/', $text)
            ? 'important'
            : 'info';
    }

    private function status(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'passed') || str_contains($text, 'enacted')) return 'passed';
        if (str_contains($text, 'legislation') || str_contains($text, 'bill')) return 'proposed';
        if (str_contains($text, 'effective') || str_contains($text, 'from 1 ') || str_contains($text, 'from 1 july')) return 'effective';

        return 'guidance';
    }
}

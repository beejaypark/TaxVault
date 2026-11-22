<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class ProductChatController
{
    private const HELP = [
        'dashboard' => [
            'label' => 'Dashboard',
            'questions' => [
                'What can I see on the Dashboard?',
                'How do I check my tax readiness?',
            ],
            'answer' => 'The Dashboard shows your tax readiness, recent activity and important next steps at a glance.',
            'pattern' => '/\b(?:dashboard|overview|tax readiness|next steps?)\b|대시보드|세금 준비/iu',
        ],
        'uploads' => [
            'label' => 'Documents',
            'questions' => [
                'What files can I upload?',
            ],
            'question' => 'What files can I upload?',
            'answer' => 'You can upload PDF, JPEG, PNG, WebP and TIFF files in Documents.',
            'pattern' => '/\b(?:file (?:types?|formats?)|pdf|jpe?g|png|webp|tiff?)\b|\b(?:upload|attach)\b.*\b(?:files?|receipts?|photos?|documents?)\b|파일 형식|파일.*업로드/iu',
        ],
        'onedrive' => [
            'label' => 'Documents',
            'questions' => [
                'Can I connect OneDrive?',
                'How do I import documents from OneDrive?',
            ],
            'question' => 'Can I connect OneDrive?',
            'answer' => 'Yes. Connect OneDrive in TaxVault’s document storage settings to import files and folders.',
            'pattern' => '/\bone\s*drive\b|원드라이브/iu',
        ],
        'accountant' => [
            'label' => 'Accountants',
            'questions' => [
                'How do I share with my accountant?',
                'How do I manage my accountant’s access?',
            ],
            'question' => 'How do I share with my accountant?',
            'answer' => 'Open Accountants in TaxVault to invite your accountant and manage their access. You can also send a tax return pack from there.',
            'pattern' => '/\b(?:share|invite|send|access)\b.*\baccountant\b|\baccountant\b.*\b(?:share|invite|send|access)\b|회계사.*(?:공유|초대|접근)/iu',
        ],
        'review' => [
            'label' => 'Reviews',
            'questions' => [
                'How do I review extracted details?',
                'Can I correct an extracted amount or date?',
            ],
            'question' => 'How do I review extracted details?',
            'answer' => 'Open Reviews to check extracted dates and amounts against the source document and correct any details.',
            'pattern' => '/\b(?:ocr|extract(?:ed|ion)?)\b|\breview\b.*\b(?:details?|amounts?|dates?)\b|추출|검토.*(?:금액|날짜)/iu',
        ],
        'financial-years' => [
            'label' => 'Financial years',
            'questions' => [
                'How are documents organised by financial year?',
                'How do I switch the active financial year?',
            ],
            'question' => 'How are documents organised by financial year?',
            'answer' => 'TaxVault groups your tax evidence by financial year so you can find source documents for the year you are reviewing.',
            'pattern' => '/\b(?:financial|tax|fiscal) years?\b|회계연도|회계 연도/',
        ],
        'return-pack' => [
            'label' => 'Tax return pack',
            'questions' => [
                'What is a tax return pack?',
                'What records are included in a tax return pack?',
            ],
            'question' => 'What is a tax return pack?',
            'answer' => 'A tax return pack brings supporting records together into a summary you can share with your accountant.',
            'pattern' => '/\b(?:tax return|tax) pack\b|세금 신고 팩/iu',
        ],
        'transactions' => [
            'label' => 'Income & expenses',
            'questions' => [
                'How do I record income and expenses?',
                'Can I attach a receipt to a transaction?',
            ],
            'answer' => 'Income and expenses can be recorded in Transactions and linked to the documents that support them.',
            'pattern' => '/\b(?:income|expense|expenses?|transactions?)\b|수입|지출|거래/iu',
        ],
        'properties' => [
            'label' => 'Properties',
            'questions' => [
                'How do I track a property?',
                'Can I record property expenses and periods?',
            ],
            'answer' => 'Properties lets you keep property details, periods and supporting expenses together for review.',
            'pattern' => '/\bproperties?\b|\brental\b|부동산|임대/iu',
        ],
        'investments' => [
            'label' => 'Investments',
            'questions' => [
                'How do I record an investment?',
                'Can I keep investment evidence in TaxVault?',
            ],
            'answer' => 'Investments lets you record investment activity and keep the evidence that supports it in the same workspace.',
            'pattern' => '/\binvestments?\b|투자/iu',
        ],
        'tax-news' => [
            'label' => 'Tax news',
            'questions' => [
                'Where can I find the latest tax news?',
                'Can TaxVault explain tax news for my situation?',
            ],
            'answer' => 'Tax News contains product-linked tax updates. TaxVault does not provide personal tax advice, so contact your accountant for advice about your situation.',
            'pattern' => '/\b(?:tax news|updates?|latest tax)\b|세금 뉴스|세금 소식/iu',
        ],
        'settings' => [
            'label' => 'Settings',
            'questions' => [
                'Where can I change my TaxVault settings?',
                'How do I manage document storage and notifications?',
            ],
            'answer' => 'Open Settings to manage document storage, notifications and your TaxVault preferences.',
            'pattern' => '/\bsettings?\b|\bpreferences?\b|설정|환경설정/iu',
        ],
    ];

    public function suggestions(): JsonResponse
    {
        $counts = DB::table('product_chat_topics')->pluck('question_count', 'topic');
        $topics = array_keys(self::HELP);
        usort($topics, fn (string $first, string $second): int =>
            (int) ($counts[$second] ?? 0) <=> (int) ($counts[$first] ?? 0));

        $categories = [];
        foreach ($topics as $topic) {
            $label = self::HELP[$topic]['label'];
            $categoryId = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $label));
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = ['id' => $categoryId, 'label' => $label, 'questions' => []];
            }
            $categories[$categoryId]['questions'] = array_values(array_unique([
                ...$categories[$categoryId]['questions'],
                ...self::HELP[$topic]['questions'],
            ]));
        }

        return response()->json(['categories' => array_values($categories)])
            ->header('Cache-Control', 'no-store');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'questions' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'questions.*' => ['required', 'string', 'max:600'],
        ]);
        $questions = $input['questions'];
        foreach ($questions as $message) {
            if (preg_match('/(?<!\d)(?:\d[ -]?){7,8}\d(?![ -]?\d)/', $message)) {
                return response()->json(['answer' => 'Please remove any tax file numbers or other personal details and ask again.']);
            }
        }
        $question = end($questions);

        foreach (self::HELP as $topic => $help) {
            $exactQuestion = in_array($question, $help['questions'], true);
            if ($exactQuestion || preg_match($help['pattern'], $question)) {
                DB::table('product_chat_topics')->upsert(
                    ['topic' => $topic, 'question_count' => 1],
                    ['topic'],
                    ['question_count' => DB::raw('product_chat_topics.question_count + 1')],
                );
                if ($exactQuestion) {
                    return response()->json(['answer' => $help['answer']]);
                }
                break;
            }
        }

        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey !== '') {
            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->connectTimeout(4)
                    ->timeout(14)
                    ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                        'model' => config('services.openai.tax_news_model'),
                        'instructions' => 'You are the TaxVault product help assistant. Answer the last question in its language using only the product facts below. Earlier questions are context. Keep the answer brief and use plain text. Never request or repeat private tax details. Decline personal tax advice. If the facts do not answer the question, direct the visitor to Contact us. TaxVault does not prepare or lodge tax returns. Product facts: '.implode(' ', array_column(self::HELP, 'answer')),
                        'input' => implode("\n", $questions),
                        'max_output_tokens' => 220,
                        'store' => false,
                    ]);

                if ($response->successful()) {
                    foreach ($response->json('output', []) as $item) {
                        foreach ($item['content'] ?? [] as $content) {
                            $text = $content['text'] ?? null;
                            if (($content['type'] ?? null) === 'output_text' && is_string($text) && trim($text) !== '') {
                                return response()->json(['answer' => trim($text)]);
                            }
                        }
                    }
                }
            } catch (ConnectionException) {
                // Keep the public help panel usable when the provider is unreachable.
            }
        }

        return response()->json(['answer' => null]);
    }
}

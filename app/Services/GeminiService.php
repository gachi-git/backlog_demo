<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * タスク情報を分析してAIコメントを生成
     *
     * @param array $taskData タスクの情報
     * @return string AIが生成したコメント
     */
    public function generateTaskComment(array $taskData): string
    {
        try {
            $prompt = $this->buildPrompt($taskData);

            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 2000,
                    ]
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $finishReason = $result['candidates'][0]['finishReason'] ?? 'UNKNOWN';

                // デバッグ: 生のレスポンスをログに記録
                Log::info('Gemini API raw response', [
                    'text' => $text,
                    'length' => mb_strlen($text),
                    'finishReason' => $finishReason,
                    'full_candidate' => $result['candidates'][0] ?? null
                ]);

                // 改行を削除して1行のコメントにする
                return trim(str_replace(["\n", "\r"], '', $text));
            }

            Log::warning('Gemini API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $this->getFallbackComment($taskData);

        } catch (\Exception $e) {
            Log::error('Gemini API error', [
                'message' => $e->getMessage()
            ]);

            return $this->getFallbackComment($taskData);
        }
    }

    /**
     * タスク情報からプロンプトを構築
     */
    private function buildPrompt(array $taskData): string
    {
        $title = $taskData['title'] ?? '不明';
        $description = $taskData['description'] ?? '';
        $priority = $taskData['priority'] ?? '中';
        $dueDate = $taskData['dueDate'] ?? null;
        $estimatedHours = $taskData['estimatedHours'] ?? null;

        $prompt = "以下のタスクについて、作業者への具体的なアドバイスを150文字以内の完結した文章（です・ます調）で出力してください。\n\n";
        $prompt .= "タイトル: {$title}\n";

        if ($description) {
            $prompt .= "説明: {$description}\n";
        }

        $prompt .= "優先度: {$priority}\n";

        if ($dueDate) {
            $prompt .= "期限: {$dueDate}\n";
        }

        if ($estimatedHours) {
            $prompt .= "見積: {$estimatedHours}時間\n";
        }

        $prompt .= "\n※重要性、着手タイミング、注意点を含め、必ず句点（。）で終えてください。\n";

        return $prompt;
    }

    /**
     * APIが使用できない場合のフォールバックコメント
     */
    private function getFallbackComment(array $taskData): string
    {
        $priority = $taskData['priority'] ?? '中';
        $dueDate = $taskData['dueDate'] ?? null;

        if ($priority === '高') {
            return '優先度が高いタスクです。早めに着手しましょう。';
        }

        if ($dueDate && \Carbon\Carbon::parse($dueDate)->diffInDays(\Carbon\Carbon::today()) <= 3) {
            return '期限が近づいています。今日中に着手することをお勧めします。';
        }

        return '計画通りに進めましょう。';
    }
}

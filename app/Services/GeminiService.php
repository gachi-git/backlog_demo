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

    /**
     * 作業パターンを分析してAIアドバイスを生成
     *
     * @param array $summary 統計データ
     * @return array AIが生成したアドバイス（JSON配列）
     */
    public function generateAnalysisAdvice(array $summary): array
    {
        try {
            $prompt = $this->buildAnalysisPrompt($summary);

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
                        'maxOutputTokens' => 3000,
                    ]
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

                Log::info('Gemini Analysis API response', [
                    'text' => $text,
                    'length' => mb_strlen($text),
                ]);

                // JSONとしてパース
                $adviceArray = $this->parseAdviceJson($text);

                // パースに成功した場合のみ返す、失敗時はフォールバック
                if (!empty($adviceArray)) {
                    return $adviceArray;
                }

                Log::warning('Gemini returned empty or invalid JSON, using fallback');
                return $this->getFallbackAnalysisAdvice($summary);
            }

            Log::warning('Gemini Analysis API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $this->getFallbackAnalysisAdvice($summary);

        } catch (\Exception $e) {
            Log::error('Gemini Analysis API error', [
                'message' => $e->getMessage()
            ]);

            return $this->getFallbackAnalysisAdvice($summary);
        }
    }

    /**
     * 統計データから分析用プロンプトを構築
     */
    private function buildAnalysisPrompt(array $summary): string
    {
        $prompt = "あなたはタスク管理の専門家です。以下の過去7日間の作業データを分析し、2〜3個の具体的なアドバイスをJSON形式で提供してください。\n\n";

        $prompt .= "【統計データ】\n";
        $prompt .= "期間: {$summary['period']['start']} ～ {$summary['period']['end']}\n";
        $prompt .= "総タスク数: {$summary['total_tasks']}件\n";
        $prompt .= "完了: {$summary['completed_tasks']}件（完了率: {$summary['completion_rate']}%）\n";
        $prompt .= "失敗: {$summary['failed_tasks']}件（失敗率: {$summary['failure_rate']}%）\n";
        $prompt .= "実行中: {$summary['in_progress_tasks']}件\n";
        $prompt .= "1日平均タスク数: {$summary['avg_tasks_per_day']}件\n";
        $prompt .= "平均実績時間: {$summary['avg_actual_minutes']}分\n\n";

        $prompt .= "【日別の推移】\n";
        foreach ($summary['daily_stats'] as $day) {
            $prompt .= "{$day['date']} ({$day['day_of_week']}): 総数{$day['total']}件, 完了{$day['completed']}件, 失敗{$day['failed']}件\n";
        }

        $prompt .= "\n【出力形式】\n";
        $prompt .= "必ず以下のJSON形式で出力してください。マークダウンのコードブロック（```json）は使わず、純粋なJSON配列のみを出力してください：\n\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"title\": \"アドバイスのタイトル\",\n";
        $prompt .= "    \"description\": \"具体的な説明（100文字以内）\",\n";
        $prompt .= "    \"tag\": \"推奨\" or \"緊急\" or \"参考\",\n";
        $prompt .= "    \"type\": \"recommend\" or \"warning\" or \"info\"\n";
        $prompt .= "  }\n";
        $prompt .= "]\n\n";
        $prompt .= "【アドバイス条件】\n";
        $prompt .= "- 作業パターンの発見（生産性が高い曜日、失敗が多い傾向など）\n";
        $prompt .= "- 具体的な改善提案（タスク量の調整、時間配分など）\n";
        $prompt .= "- ポジティブで実践的なトーン、です・ます調\n";
        $prompt .= "- 各description は100文字以内\n";
        $prompt .= "- 2〜3個のアドバイスを生成\n";

        return $prompt;
    }

    /**
     * Gemini APIからのレスポンスをパースしてJSON配列にする
     */
    private function parseAdviceJson(string $text): array
    {
        // マークダウンのコードブロックを削除
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        $text = trim($text);

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

            // 配列であることを確認
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        } catch (\JsonException $e) {
            Log::warning('Failed to parse Gemini advice JSON', [
                'error' => $e->getMessage(),
                'text' => $text
            ]);
        }

        // パース失敗時はフォールバックを返す
        return [];
    }

    /**
     * APIが使用できない場合のフォールバック分析アドバイス
     */
    private function getFallbackAnalysisAdvice(array $summary): array
    {
        $completionRate = $summary['completion_rate'];
        $failureRate = $summary['failure_rate'];

        $advices = [];

        if ($completionRate >= 80) {
            $advices[] = [
                'title' => '素晴らしい進捗！',
                'description' => "完了率{$completionRate}%と高いパフォーマンスを維持しています。この調子で継続していきましょう。",
                'tag' => '参考',
                'type' => 'info'
            ];
        } elseif ($failureRate > 20) {
            $advices[] = [
                'title' => 'タスク計画の見直しを推奨',
                'description' => "失敗率が{$failureRate}%とやや高めです。タスクの見積もりや優先度を見直し、無理のない計画を立てることをお勧めします。",
                'tag' => '緊急',
                'type' => 'warning'
            ];
        } else {
            $advices[] = [
                'title' => '着実な進捗',
                'description' => "完了率{$completionRate}%で着実に進んでいます。計画的にタスクを進めていきましょう。",
                'tag' => '参考',
                'type' => 'info'
            ];
        }

        return $advices;
    }
}

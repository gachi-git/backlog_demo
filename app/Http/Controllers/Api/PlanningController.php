<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\RawBacklogIssue;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function __construct(
        private GeminiService $geminiService
    ) {
    }
    /**
     * AI計画生成
     * POST /api/planning/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $userId = null; // 認証未実装のため null（単一ユーザー想定）
        $today = Carbon::today();

        // 1. 既存の実行していない計画をクリア（今日以降の予定）
        DailyPlan::whereNull('user_id')
            ->where('target_date', '>=', $today)
            ->where('result_status', 'pending')
            ->delete();

        // 2. 未完了の課題を取得
        $incompleteIssues = RawBacklogIssue::incomplete()
            ->get()
            ->sortBy(function ($issue) {
                // 優先度でソート（高→中→低）
                $priorityOrder = ['高' => 1, '中' => 2, '低' => 3];
                $priorityName = $issue->data['priority']['name'] ?? '中';
                $priority = $priorityOrder[$priorityName] ?? 2;

                // 期限日でソート（近い方が優先）
                $dueDate = $issue->data['dueDate'] ?? '9999-12-31';

                return [$priority, $dueDate];
            })
            ->take(3); // 上位3件を今日の計画に追加（開発中）

        // 3. 計画を自動生成
        $createdPlans = [];
        $totalIssues = $incompleteIssues->count();
        foreach ($incompleteIssues as $index => $issue) {
            $estimatedHours = $issue->data['estimatedHours'] ?? 2;
            $plannedMinutes = $estimatedHours * 60;

            $plan = DailyPlan::create([
                'raw_issue_id' => $issue->id,
                'user_id' => $userId,
                'target_date' => $today,
                'lane_status' => 'planned',
                'result_status' => 'pending',
                'planned_minutes' => $plannedMinutes,
                'ai_comment' => $this->generateAiComment($issue),
            ]);

            $createdPlans[] = [
                'id' => $plan->id,
                'issue_key' => $issue->issue_key,
                'title' => $issue->data['summary'] ?? '',
                'planned_minutes' => $plannedMinutes,
                'priority' => $issue->data['priority']['name'] ?? '中',
                'ai_comment' => $plan->ai_comment,
            ];

            // レート制限を回避するため、最後以外は5秒待機
            if ($index < $totalIssues - 1) {
                sleep(5);
            }
        }

        // 4. 生成結果を返す
        return response()->json([
            'success' => true,
            'message' => count($createdPlans) . '件の計画を生成しました',
            'plans' => $createdPlans,
            'target_date' => $today->format('Y-m-d'),
        ]);
    }

    /**
     * AIコメントを生成
     */
    private function generateAiComment(RawBacklogIssue $issue): string
    {
        $taskData = [
            'title' => $issue->data['summary'] ?? '',
            'description' => $issue->data['description'] ?? '',
            'priority' => $issue->data['priority']['name'] ?? '中',
            'dueDate' => $issue->data['dueDate'] ?? null,
            'estimatedHours' => $issue->data['estimatedHours'] ?? null,
        ];

        return $this->geminiService->generateTaskComment($taskData);
    }
}

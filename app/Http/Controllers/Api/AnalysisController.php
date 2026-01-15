<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\DailyPlan;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function __construct(
        private GeminiService $geminiService
    ) {
    }

    /**
     * AIアドバイスを生成
     * POST /api/analysis/advice
     *
     * @param Request $request
     *   - date: 分析対象日（省略時は今日）
     *   - refresh: true の場合、キャッシュを無視して再生成
     */
    public function generateAdvice(Request $request): JsonResponse
    {
        $targetDate = $request->input('date', Carbon::today()->format('Y-m-d'));
        $forceRefresh = $request->input('refresh', false);

        // キャッシュチェック: refresh=false かつ既存データがあれば返す
        if (!$forceRefresh) {
            $existingAnalysis = AiAnalysis::findByDate($targetDate);
            if ($existingAnalysis) {
                return response()->json([
                    'success' => true,
                    'cached' => true,
                    'data' => [
                        'target_date' => $existingAnalysis->target_date->format('Y-m-d'),
                        'advice' => $existingAnalysis->advice_text,
                    ],
                ]);
            }
        }

        // refresh=true の場合、既存のキャッシュを削除
        if ($forceRefresh) {
            AiAnalysis::where('target_date', $targetDate)->delete();
        }

        // 1. daily_plansから統計データを集計
        $summary = $this->calculateSummary($targetDate);

        // 2. Geminiでアドバイスを生成
        $advice = $this->geminiService->generateAnalysisAdvice($summary);

        // 3. DBに保存
        $analysis = AiAnalysis::create([
            'target_date' => $targetDate,
            'summary_json' => $summary,
            'advice_text' => $advice,
        ]);

        return response()->json([
            'success' => true,
            'cached' => false,
            'data' => [
                'target_date' => $analysis->target_date->format('Y-m-d'),
                'advice' => $analysis->advice_text,
            ],
        ]);
    }

    /**
     * daily_plansから統計データを集計
     */
    private function calculateSummary(string $targetDate): array
    {
        // 対象日から過去7日間のデータを取得
        $endDate = Carbon::parse($targetDate);
        $startDate = $endDate->copy()->subDays(6);

        $plans = DailyPlan::whereBetween('target_date', [$startDate, $endDate])->get();

        $totalTasks = $plans->count();
        $completedTasks = $plans->where('result_status', 'completed')->count();
        $failedTasks = $plans->where('result_status', 'failed')->count();
        $inProgressTasks = $plans->where('lane_status', 'in_progress')->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        $failureRate = $totalTasks > 0 ? round(($failedTasks / $totalTasks) * 100) : 0;

        // 1日あたりの平均タスク数
        $avgTasksPerDay = round($totalTasks / 7, 1);

        // 平均実績時間（完了したタスクのみ）
        $completedPlans = $plans->where('result_status', 'completed')->where('actual_minutes', '>', 0);
        $avgActualMinutes = $completedPlans->count() > 0
            ? round($completedPlans->avg('actual_minutes'))
            : 0;

        // 曜日別の傾向
        $dailyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i);
            $dayPlans = $plans->where('target_date', $date->format('Y-m-d'));

            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'day_of_week' => $date->format('D'),
                'total' => $dayPlans->count(),
                'completed' => $dayPlans->where('result_status', 'completed')->count(),
                'failed' => $dayPlans->where('result_status', 'failed')->count(),
            ];
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'failed_tasks' => $failedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'completion_rate' => $completionRate,
            'failure_rate' => $failureRate,
            'avg_tasks_per_day' => $avgTasksPerDay,
            'avg_actual_minutes' => $avgActualMinutes,
            'daily_stats' => $dailyStats,
        ];
    }

    /**
     * 統計サマリーを取得
     * GET /api/analysis/summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $targetDate = $request->input('date', Carbon::today()->format('Y-m-d'));
        $summary = $this->calculateSummary($targetDate);

        return response()->json([
            'total_tasks' => $summary['total_tasks'],
            'completion_rate' => $summary['completion_rate'],
            'in_progress' => $summary['in_progress_tasks'],
            'failure_rate' => $summary['failure_rate'],
            'period' => $targetDate,
        ]);
    }

    /**
     * 週次進捗データを取得
     * GET /api/analysis/weekly-progress
     */
    public function getWeeklyProgress(Request $request): JsonResponse
    {
        $targetDate = $request->input('date', Carbon::today()->format('Y-m-d'));
        $summary = $this->calculateSummary($targetDate);

        return response()->json($summary['daily_stats']);
    }

    /**
     * カテゴリ別完了率を取得
     * GET /api/analysis/categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        $targetDate = $request->input('date', Carbon::today()->format('Y-m-d'));
        $endDate = Carbon::parse($targetDate);
        $startDate = $endDate->copy()->subDays(6);

        // daily_plansとraw_backlog_issuesを結合してカテゴリ別に集計
        $plans = DailyPlan::with('rawBacklogIssue')
            ->whereBetween('target_date', [$startDate, $endDate])
            ->get();

        $categories = [];

        foreach ($plans as $plan) {
            if (!$plan->rawBacklogIssue || !isset($plan->rawBacklogIssue->data['category'])) {
                continue;
            }

            $categoryData = $plan->rawBacklogIssue->data['category'];

            // カテゴリが配列の場合（複数カテゴリ）は最初のものを使用
            if (is_array($categoryData)) {
                $categoryName = $categoryData[0]['name'] ?? 'その他';
            } else {
                $categoryName = $categoryData['name'] ?? 'その他';
            }

            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'name' => $categoryName,
                    'total' => 0,
                    'completed' => 0,
                ];
            }

            $categories[$categoryName]['total']++;

            if ($plan->result_status === 'completed') {
                $categories[$categoryName]['completed']++;
            }
        }

        // 完了率を計算
        $result = array_map(function ($category) {
            $completionRate = $category['total'] > 0
                ? round(($category['completed'] / $category['total']) * 100)
                : 0;

            return [
                'name' => $category['name'],
                'total' => $category['total'],
                'completed' => $category['completed'],
                'completion_rate' => $completionRate,
            ];
        }, $categories);

        return response()->json(array_values($result));
    }
}

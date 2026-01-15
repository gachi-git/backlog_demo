<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
// ▼▼▼ 追加: モデルを使用できるようにインポート ▼▼▼
use App\Models\DailyPlan;
use App\Models\RawBacklogIssue;

class PlanningController extends Controller
{
    // ==========================================
    //  画面表示用（HTMLを返す）
    // ==========================================

    /**
     * 1. 計画ダッシュボード (カンバン & KPI)
     * URL: /planning
     */
    public function index(Request $request)
    {
        $userId = Auth::id() ?? 1;

        $tasks = DB::table('daily_plans')
            ->select('id', 'lane_status', 'target_date', 'end_date', 'raw_issue_id')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => 'タスク ID:' . $plan->id, 
                    'lane_status' => $plan->lane_status, // DBのカラム名
                    'status' => $plan->lane_status,      // フロント用
                    'plan_type' => 'work',
                    'scheduled_time' => '10:00',
                    'end_time' => '11:00',
                    'duration_minutes' => 60,
                    'target_date' => $plan->target_date,
                    'end_date' => $plan->end_date,
                ];
            });

        $importedIssues = DB::table('raw_backlog_issues')->limit(5)->get();

        $stats = [
            'pending_issues' => $importedIssues->count(),
            'today_plans' => $tasks->count(),
            'today_hours' => $tasks->sum('duration_minutes') / 60,
            'week_plans' => 0 
        ];

        // ガントチャート用データ整形
        $ganttTasks = $tasks->map(function($task) {
            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'start_date' => $task['target_date'] ?? now()->format('Y-m-d'),
                'end_date' => $task['end_date'] ?? $task['target_date'] ?? now()->format('Y-m-d'),
                'status' => 'blue',
            ];
        });

        // 画面(View)を返す
        return view('planning.index', [
            'tasks' => $tasks,
            'stats' => $stats,
            'importedIssues' => $importedIssues,
            'weekPlans' => collect(),
            'ganttTasks' => $ganttTasks,
            'year' => $request->input('year', now()->year),
            'month' => $request->input('month', now()->month),
        ]);
    }

    /**
     * 3. タイムライン表示
     * URL: /planning/timeline
     */
    public function timeline(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : today();
        
        $plans = DB::table('daily_plans')
            ->whereDate('target_date', $date)
            ->orderBy('id')
            ->get()
            ->map(function ($plan) {
                $plan->title = 'タスク ID:' . $plan->id;
                $plan->status = $plan->lane_status; 
                $plan->scheduled_time = Carbon::parse('10:00'); 
                $plan->end_time = Carbon::parse('11:00');
                $plan->duration_minutes = 60;
                $plan->plan_type = 'work';
                return $plan;
            });

        $timeSlots = [];
        for ($hour = 6; $hour <= 23; $hour++) {
            $timeSlots[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'plans' => $plans->filter(function($plan) use ($hour) {
                    return $plan->scheduled_time->hour === $hour;
                })
            ];
        }

        return view('planning.timeline', compact('plans', 'date', 'timeSlots'));
    }

    /**
     * 4. カレンダー表示
     * URL: /planning/calendar
     */
    public function calendar(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        
        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $startOfCalendar = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $endOfCalendar = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        $tasks = DB::table('daily_plans')
            ->whereBetween('target_date', [$startOfCalendar->format('Y-m-d'), $endOfCalendar->format('Y-m-d')])
            ->get();

        $calendar = [];
        $currentDate = $startOfCalendar->copy();

        while ($currentDate->lte($endOfCalendar)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayTasks = $tasks->filter(function($task) use ($dateStr) {
                    return $task->target_date === $dateStr;
                })->map(function($task) {
                    $task->title = 'タスク ID:' . $task->id;
                    $task->target_date = Carbon::parse($task->target_date);
                    $task->status = $task->lane_status;
                    $task->plan_type = 'work';
                    $task->scheduled_time = Carbon::parse('10:00'); 
                    $task->duration_minutes = 60;
                    return $task;
                });

                $week[] = [
                    'date' => $currentDate->copy(),
                    'day' => $currentDate->day, 
                    'isCurrentMonth' => $currentDate->month == $month,
                    'isToday' => $currentDate->isToday(),
                    'plans' => $dayTasks 
                ];
                $currentDate->addDay();
            }
            $calendar[] = $week;
        }

        return view('planning.calendar', compact('year', 'month', 'calendar'));
    }

    /**
     * 5. ガントチャート表示
     * URL: /planning/gantt
     */
    public function gantt(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        
        $ganttTasks = DB::table('daily_plans')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => 'タスク ID:' . $plan->id,
                    'start_date' => $plan->target_date ?? now()->format('Y-m-d'),
                    'end_date' => $plan->end_date ?? $plan->target_date ?? now()->format('Y-m-d'),
                    'type' => 'work',
                ];
            });

        return view('planning.gantt', compact('ganttTasks', 'year', 'month'));
    }

    /**
     * 2. AI計画生成処理 (フォーム送信後のリダイレクト)
     * URL: /planning/generate
     */
    public function generate(Request $request)
    {
        $userId = Auth::id() ?? 1;
        $issues = DB::table('raw_backlog_issues')->get();

        if ($issues->isEmpty()) {
            return redirect()->back()->with('warning', '課題がありません');
        }

        DB::table('daily_plans')
            ->where('user_id', $userId)
            ->where('lane_status', 'planned')
            ->delete();

        foreach ($issues as $issue) {
            DB::table('daily_plans')->insert([
                'user_id' => $userId,
                'raw_issue_id' => $issue->id,
                'target_date' => now(),
                'end_date' => now(), 
                'lane_status' => 'planned',
                'planned_minutes' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('planning.index')->with('success', 'AI計画を生成しました！');
    }

    // ==========================================
    //  API用（JSONを返す / Alpine.jsから呼ばれる）
    // ==========================================

    /**
     * 6. API: ガントチャート日付更新
     * URL: /api/tasks/{id}/update-dates
     */
    public function updateDates(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        DB::table('daily_plans')
            ->where('id', $id)
            ->update([
                'target_date' => $request->start_date,
                'end_date' => $request->end_date,
                'updated_at' => now()
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * 7. API: カンバンステータス更新
     * URL: /api/planning/tasks/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed,skipped'
        ]);

        DB::table('daily_plans')
            ->where('id', $id)
            ->update([
                'lane_status' => $validated['status'],
                'updated_at' => now()
            ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'id' => $id,
            'new_status' => $validated['status']
        ]);
    }

    /**
     * 8. API: 今日のタスクボード用データ取得
     * URL: /api/planning/daily
     */
    public function getDailyTasks(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $userId = $request->input('user_id', 1);

        // モデルを使ってリレーション(rawBacklogIssue)も含めて取得
        $plans = DailyPlan::with('rawBacklogIssue')
            ->where('target_date', $date)
            ->where('user_id', $userId)
            ->get();

        // ステータスごとにグループ化
        $grouped = $plans->groupBy('lane_status');

        return response()->json([
            'date' => $date,
            'lanes' => [
                'planned'     => $grouped->get('planned', []),
                'in_progress' => $grouped->get('in_progress', []),
                'completed'   => $grouped->get('completed', []),
                'skipped'     => $grouped->get('skipped', []),
            ]
        ]);
    }

    /**
     * 9. API: 未消化の課題リスト取得
     * URL: /api/planning/unscheduled
     */
    public function getUnscheduled(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $userId = $request->input('user_id', 1);

        // すでに「今日の計画」に入っている課題IDのリストを作る
        $plannedIssueIds = DailyPlan::where('target_date', $date)
            ->where('user_id', $userId)
            ->pluck('raw_issue_id');

        // 今日の計画に入っていない課題を取得
        $unscheduledIssues = RawBacklogIssue::whereNotIn('id', $plannedIssueIds)->get();

        return response()->json($unscheduledIssues);
    }
}
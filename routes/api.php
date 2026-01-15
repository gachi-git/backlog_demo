<?php


use Illuminate\Support\Facades\Route;

// コントローラーのインポート
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\PlanningController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| データ（JSON）を返すルート
| URLの先頭に自動的に /api が付く
*/

//Route::middleware('auth:sanctum')->group(function () {

    // 1. カンバン移動（ステータス更新）
    // URL: /api/planning/tasks/{id}/status
    Route::patch('/planning/tasks/{id}/status', [PlanningController::class, 'updateStatus']);

    // 2. ガント操作（日付更新）
    // URL: /api/tasks/{id}/update-dates
    Route::post('/tasks/{id}/update-dates', [PlanningController::class, 'updateDates']);

//});

// 3. AI計画生成
Route::post('/planning/generate', [PlanningController::class, 'generate']);

// 4. 今日のタスク（カンバン用）
Route::get('/planning/daily', [PlanningController::class, 'getDailyTasks']);

// 5. 未消化の課題（サイドバー用）
Route::get('/planning/unscheduled', [PlanningController::class, 'getUnscheduled']);

// 6. AI分析
Route::post('/analysis/advice', [AnalysisController::class, 'generateAdvice']);

// 7. 統計サマリー
Route::get('/analysis/summary', [AnalysisController::class, 'getSummary']);

// 8. 週次進捗
Route::get('/analysis/weekly-progress', [AnalysisController::class, 'getWeeklyProgress']);

// 9. カテゴリ別完了率
Route::get('/analysis/categories', [AnalysisController::class, 'getCategories']);
<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanningController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
<<<<<<< HEAD
| データ（JSON）を返すルート
| URLの先頭に自動的に /api が付く「
*/

Route::middleware('auth:sanctum')->group(function () {

    // 1. カンバン移動（ステータス更新）
    // URL: /api/planning/tasks/{id}/status
    Route::patch('/planning/tasks/{id}/status', [PlanningController::class, 'updateStatus']);

    // 2. ガント操作（日付更新）
    // URL: /api/tasks/{id}/update-dates
    Route::post('/tasks/{id}/update-dates', [PlanningController::class, 'updateDates']);

});

// AI計画生成
Route::post('/planning/generate', [PlanningController::class, 'generate']);

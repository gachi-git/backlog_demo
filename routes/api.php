<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\PlanningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// AI計画生成
Route::post('/planning/generate', [PlanningController::class, 'generate']);

// AI分析
Route::post('/analysis/advice', [AnalysisController::class, 'generateAdvice']);

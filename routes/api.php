<?php

use App\Http\Controllers\Api\PlanningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// AI計画生成
Route::post('/planning/generate', [PlanningController::class, 'generate']);

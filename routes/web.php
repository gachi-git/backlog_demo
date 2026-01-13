<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;




    // ▼ 計画・タスク管理画面（Viewを返す）
    Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::get('planning/timeline', [PlanningController::class, 'timeline'])->name('planning.timeline');
    Route::get('planning/calendar', [PlanningController::class, 'calendar'])->name('planning.calendar');
    Route::get('planning/gantt', [PlanningController::class, 'gantt'])->name('planning.gantt');

    // 生成処理（リダイレクトあり）
    Route::post('planning/generate', [PlanningController::class, 'generate'])->name('planning.generate');

   

// ※ auth.phpがない場合はコメントアウト
// require __DIR__.'/auth.php';
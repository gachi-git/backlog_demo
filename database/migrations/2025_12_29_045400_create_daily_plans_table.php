<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();

            // ▼ 外部キー制約
            $table->foreignId('raw_issue_id')->nullable()->constrained('raw_backlog_issues')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // ▼ 日付まわり（File Bの end_date を採用してガントチャートに対応）
            $table->date('target_date')->nullable()->comment('計画日（開始日）');
            $table->date('end_date')->nullable()->comment('終了日（ガントチャート用）');

            // ▼ ステータス（カンバン用）
            // 'skipped' 
            $table->string('lane_status')->default('planned')->comment('planned/in_progress/completed/skipped');
            $table->string('result_status')->default('pending')->comment('結果: completed/failed/pending');

            // ▼ 数値・AIコメント
            $table->integer('planned_minutes')->nullable()->comment('予定時間（分）');
            $table->integer('actual_minutes')->nullable()->comment('実績時間（分）');
            $table->text('ai_comment')->nullable()->comment('AIからの一言アドバイス');

            $table->timestamps();

            // ▼ インデックス（File Aの高速化設定を採用）
            
            $table->index('target_date');
            $table->index(['user_id', 'target_date']);
            $table->index('lane_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
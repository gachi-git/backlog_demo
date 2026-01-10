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
            $table->foreignId('raw_issue_id')->constrained('raw_backlog_issues')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('target_date')->comment('計画日');
            $table->string('lane_status')->default('planned')->comment('カンバンのレーン: planned/in_progress/completed');
            $table->string('result_status')->default('pending')->comment('結果: completed/failed/pending');
            $table->integer('planned_minutes')->nullable()->comment('予定時間（分）');
            $table->integer('actual_minutes')->nullable()->comment('実績時間（分）');
            $table->text('ai_comment')->nullable()->comment('AIからの一言アドバイス');
            $table->timestamps();

            // インデックス
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

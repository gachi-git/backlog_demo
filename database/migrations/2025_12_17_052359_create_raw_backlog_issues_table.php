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
        Schema::create('raw_backlog_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backlog_id')->unique(); // Backlogの課題ID
            $table->string('issue_key')->unique(); // 課題キー (例: PROJ-123)
            $table->json('data'); // Backlog APIレスポンスの生データ
            $table->timestamp('synced_at'); // 同期日時
            $table->timestamp('updated_at_backlog'); // Backlogでの最終更新日時
            $table->softDeletes(); // ソフトデリート（Backlogで削除された課題用）
            $table->timestamps();

            $table->index('backlog_id');
            $table->index('issue_key');
            $table->index('updated_at_backlog');
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_backlog_issues');
    }
};

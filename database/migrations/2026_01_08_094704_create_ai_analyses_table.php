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
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->date('target_date')->comment('分析対象日');
            $table->json('summary_json')->nullable()->comment('集計数値（完了率、件数など）');
            $table->json('advice_text')->nullable()->comment('AIからの構造化アドバイス（JSON配列）');
            $table->timestamps();

            // インデックス
            $table->index('target_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};

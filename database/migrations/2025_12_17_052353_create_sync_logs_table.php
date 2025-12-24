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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type'); // 'issues', 'comments', etc.
            $table->timestamp('last_synced_at')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'running', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->integer('total_fetched')->default(0);
            $table->timestamps();

            $table->index(['resource_type', 'last_synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};

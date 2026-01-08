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
            $table->unsignedBigInteger('raw_issue_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('target_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('lane_status')->default('planned');
            
            $table->string('result_status')->nullable(); 
            $table->integer('planned_minutes')->nullable();
            $table->integer('actual_minutes')->nullable();
            $table->text('ai_comment')->nullable();
            $table->timestamps();
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
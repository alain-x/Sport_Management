<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_metrics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('player_id')->index();
            $table->date('test_date');
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('top_speed', 4, 1)->nullable();
            $table->unsignedTinyInteger('stamina')->nullable();
            $table->unsignedTinyInteger('strength')->nullable();
            $table->unsignedTinyInteger('agility')->nullable();
            $table->unsignedTinyInteger('body_fat')->nullable();
            $table->enum('fitness', ['excellent', 'good', 'fair', 'poor', 'injured'])->default('good');
            $table->text('notes')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('player_welfare', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('player_id')->index();
            $table->date('assessment_date');
            $table->unsignedTinyInteger('confidence');
            $table->unsignedTinyInteger('focus');
            $table->unsignedTinyInteger('motivation');
            $table->unsignedTinyInteger('stress');
            $table->enum('wellbeing', ['great', 'good', 'okay', 'low', 'struggling'])->default('good');
            $table->text('notes')->nullable();
            $table->unsignedInteger('assessed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('assessed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_welfare');
        Schema::dropIfExists('player_metrics');
    }
};

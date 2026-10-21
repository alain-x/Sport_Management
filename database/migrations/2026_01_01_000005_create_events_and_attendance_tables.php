<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 150);
            $table->enum('type', ['training', 'meeting', 'travel', 'deadline', 'media', 'other'])->default('training');
            $table->dateTime('starts_at');
            $table->unsignedInteger('team_id')->nullable()->index();
            $table->unsignedInteger('tournament_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('starts_at');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Training attendance: one row per player per session, optional check-in photo.
        Schema::create('attendance', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('player_id')->index();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_id', 'player_id']);
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('sport', 60)->default('Football');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed'])->default('upcoming');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tournament_id')->nullable()->index();
            $table->unsignedInteger('home_team_id')->index();
            $table->unsignedInteger('away_team_id')->index();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->dateTime('match_date')->nullable();
            $table->string('venue', 150)->nullable();
            $table->enum('status', ['scheduled', 'live', 'finished'])->default('scheduled');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('set null');
            $table->foreign('home_team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('away_team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('tournaments');
    }
};

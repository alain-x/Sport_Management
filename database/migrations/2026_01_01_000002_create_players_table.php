<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('team_id')->index();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->unsignedTinyInteger('jersey_number')->nullable();
            $table->string('position', 40)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 80)->nullable();
            $table->enum('foot', ['left', 'right', 'both'])->nullable();
            $table->string('photo')->nullable();
            // season statistics
            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('appearances')->default(0);
            $table->unsignedInteger('minutes_played')->default(0);
            $table->unsignedTinyInteger('yellow_cards')->default(0);
            $table->unsignedTinyInteger('red_cards')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });

        // Player login accounts (1:1 with a player). Added here so the FK target exists.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
        });

        Schema::dropIfExists('players');
    }
};

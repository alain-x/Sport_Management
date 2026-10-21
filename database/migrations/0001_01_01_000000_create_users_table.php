<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'manager', 'player'])->default('manager');
            $table->unsignedInteger('team_id')->nullable()->index();
            $table->unsignedInteger('player_id')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('photo')->nullable();
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        // Breeze password resets + the database session table.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

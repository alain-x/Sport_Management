<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120);
            $table->enum('type', ['team', 'group'])->default('team');
            $table->unsignedInteger('team_id')->nullable()->index();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('chat_room_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('room_id');
            $table->unsignedInteger('user_id');

            $table->unique(['room_id', 'user_id']);
            $table->foreign('room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('room_id')->index();
            $table->unsignedInteger('user_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_room_members');
        Schema::dropIfExists('chat_rooms');
    }
};

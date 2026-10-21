<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120)->unique();
            $table->string('code', 8);
            $table->string('coach', 120)->nullable();
            $table->string('city', 100)->nullable();
            $table->smallInteger('founded_year')->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};

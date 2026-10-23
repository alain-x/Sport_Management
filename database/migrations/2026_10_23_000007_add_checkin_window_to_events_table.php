<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event check-in window.
 *
 *  - checkin_opens_minutes: how many minutes before the start time players
 *    may begin checking in (earlier attempts are rejected).
 *  - checkin_late_minutes: how many minutes past the start time a check-in
 *    still counts as "present" (later check-ins are recorded as "late").
 *
 * Team managers can adjust these per event; defaults match the league rule
 * (opens 20 minutes early, late after 10 minutes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('checkin_opens_minutes')
                ->default(20)
                ->after('starts_at');
            $table->unsignedSmallInteger('checkin_late_minutes')
                ->default(10)
                ->after('checkin_opens_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['checkin_opens_minutes', 'checkin_late_minutes']);
        });
    }
};

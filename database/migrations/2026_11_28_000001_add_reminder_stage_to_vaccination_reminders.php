<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vaccination_reminders', 'reminder_stage')) {
            Schema::table('vaccination_reminders', function (Blueprint $table) {
                // Existing deliveries belong to the original reminder window.
                $table->string('reminder_stage', 32)->default('initial');
            });
        } else {
            Schema::table('vaccination_reminders', function (Blueprint $table) {
                $table->string('reminder_stage', 32)->default('initial')->change();
            });
        }

        // MySQL may use the original composite index for the child foreign key.
        // Give that constraint its own index before replacing the unique index.
        if (! Schema::hasIndex('vaccination_reminders', 'vaccination_reminders_child_stage_index')) {
            Schema::table('vaccination_reminders', function (Blueprint $table) {
                $table->index('child_profile_id', 'vaccination_reminders_child_stage_index');
            });
        }

        if (Schema::hasIndex('vaccination_reminders', 'vaccination_reminders_unique')) {
            Schema::table('vaccination_reminders', function (Blueprint $table) {
                $table->dropUnique('vaccination_reminders_unique');
            });
        }

        if (! Schema::hasIndex('vaccination_reminders', 'vaccination_reminders_stage_unique')) {
            Schema::table('vaccination_reminders', function (Blueprint $table) {
                $table->unique(['child_profile_id', 'parent_id', 'vaccine_name', 'dose_number', 'due_at', 'channel', 'reminder_stage'], 'vaccination_reminders_stage_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vaccination_reminders', function (Blueprint $table) {
            $table->dropUnique('vaccination_reminders_stage_unique');
            $table->dropColumn('reminder_stage');
            // Multiple stages may now exist for the same dose. Keep the history
            // rather than restoring a constraint that would reject those rows.
        });
    }
};

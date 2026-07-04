<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_id')->constrained('users')->cascadeOnDelete();
            $table->string('vaccine_name');
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->date('due_at');
            $table->string('channel');
            $table->string('recipient');
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['child_profile_id', 'parent_id', 'vaccine_name', 'dose_number', 'due_at', 'channel'], 'vaccination_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_reminders');
    }
};

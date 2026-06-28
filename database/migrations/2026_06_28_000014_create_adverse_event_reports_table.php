<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adverse_event_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vaccination_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vaccine_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('severity')->default('mild');
            $table->string('outcome')->nullable();
            $table->text('symptoms');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['child_profile_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adverse_event_reports');
    }
};

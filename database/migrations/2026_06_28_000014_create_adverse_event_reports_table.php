<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adverse_event_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccination_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('vaccine_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('reported_by')->constrained('users')->cascadeOnDelete();
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

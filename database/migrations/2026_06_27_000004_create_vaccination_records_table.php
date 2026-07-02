<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccine_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->date('administered_at');
            $table->date('next_due_at')->nullable();
            $table->string('suggested_vaccine')->nullable();
            $table->text('suggestion_note')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['child_profile_id', 'administered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_records');
    }
};

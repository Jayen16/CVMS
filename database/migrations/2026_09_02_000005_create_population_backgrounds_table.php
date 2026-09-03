<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_backgrounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('municipality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reference_year');
            $table->string('age_group');
            $table->string('sex');
            $table->unsignedInteger('target_population');
            $table->string('source');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['reference_year', 'municipality_id', 'barangay_id'], 'population_bg_location_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_backgrounds');
    }
};

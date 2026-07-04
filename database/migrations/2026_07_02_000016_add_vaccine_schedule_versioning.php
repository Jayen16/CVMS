<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_schedule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('version_code')->unique();
            $table->date('effective_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('vaccine_schedules', function (Blueprint $table) {
            $table->foreignUuid('vaccine_schedule_version_id')->nullable()->after('vaccine_type_id')->constrained('vaccine_schedule_versions')->cascadeOnDelete();
        });

        $version = config('immunization.version', []);
        $versionId = (string) Str::uuid();
        DB::table('vaccine_schedule_versions')->insert([
            'id' => $versionId,
            'name' => $version['name'] ?? 'PIDSP 2026',
            'version_code' => $version['version_code'] ?? '2026.0',
            'effective_date' => $version['effective_date'] ?? '2026-01-01',
            'status' => $version['status'] ?? 'active',
            'source' => config('immunization.source'),
            'source_url' => config('immunization.source_url'),
            'notes' => $version['notes'] ?? 'Seeded default schedule version.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vaccine_schedules')
            ->whereNull('vaccine_schedule_version_id')
            ->update(['vaccine_schedule_version_id' => $versionId]);

        Schema::table('vaccine_schedules', function (Blueprint $table) {
            $table->dropUnique('vaccine_schedules_vaccine_type_id_dose_number_unique');
            $table->unique(
                ['vaccine_schedule_version_id', 'vaccine_type_id', 'dose_number'],
                'vaccine_schedules_version_vaccine_dose_unique'
            );
        });

        Schema::create('child_vaccine_series_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccine_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccine_schedule_version_id')->constrained('vaccine_schedule_versions')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('assignment_reason')->nullable();
            $table->timestamps();

            $table->unique(['child_profile_id', 'vaccine_type_id'], 'child_vaccine_series_unique');
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->foreignUuid('suggested_schedule_version_id')->nullable()->after('suggested_vaccine')->constrained('vaccine_schedule_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_schedule_version_id');
        });

        Schema::dropIfExists('child_vaccine_series_versions');

        Schema::table('vaccine_schedules', function (Blueprint $table) {
            $table->dropUnique('vaccine_schedules_version_vaccine_dose_unique');
            $table->unique(['vaccine_type_id', 'dose_number'], 'vaccine_schedules_vaccine_type_id_dose_number_unique');
            $table->dropConstrainedForeignId('vaccine_schedule_version_id');
        });

        Schema::dropIfExists('vaccine_schedule_versions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaccine_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('dose_number');
            $table->unsignedSmallInteger('age_days')->default(0);
            $table->unsignedSmallInteger('age_weeks')->default(0);
            $table->unsignedSmallInteger('age_months')->default(0);
            $table->unsignedSmallInteger('age_years')->default(0);
            $table->string('label');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['vaccine_type_id', 'dose_number']);
        });

        $this->seedFromConfig();
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_schedules');
    }

    private function seedFromConfig(): void
    {
        $schedule = config('immunization.routine_schedule');

        if (! is_array($schedule)) {
            return;
        }

        foreach ($schedule as $code => $doses) {
            if (! is_string($code) || ! is_array($doses)) {
                continue;
            }

            $vaccineId = DB::table('vaccine_types')->where('code', $code)->value('id');

            if ($vaccineId === null) {
                continue;
            }

            foreach ($doses as $dose) {
                if (! is_array($dose) || ! isset($dose['dose'], $dose['age'], $dose['label']) || ! is_array($dose['age'])) {
                    continue;
                }

                DB::table('vaccine_schedules')->insert([
                    'vaccine_type_id' => $vaccineId,
                    'dose_number' => (int) $dose['dose'],
                    'age_days' => (int) ($dose['age']['days'] ?? 0),
                    'age_weeks' => (int) ($dose['age']['weeks'] ?? 0),
                    'age_months' => (int) ($dose['age']['months'] ?? 0),
                    'age_years' => (int) ($dose['age']['years'] ?? 0),
                    'label' => (string) $dose['label'],
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

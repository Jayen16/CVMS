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
        Schema::create('vaccine_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $vaccines = config('immunization.vaccines');

        if (is_array($vaccines)) {
            DB::table('vaccine_types')->insert(
                array_map(fn (array $vaccine) => [
                    'id' => (string) Str::uuid(),
                    'code' => $vaccine['code'],
                    'name' => $vaccine['name'],
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $vaccines)
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_types');
    }
};

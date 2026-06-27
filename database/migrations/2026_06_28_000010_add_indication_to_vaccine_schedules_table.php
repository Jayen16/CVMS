<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccine_schedules', function (Blueprint $table) {
            $table->string('indication')->default('routine_vaccination')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('vaccine_schedules', function (Blueprint $table) {
            $table->dropColumn('indication');
        });
    }
};

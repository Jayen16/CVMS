<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->text('nurse_remarks')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->dropColumn('nurse_remarks');
        });
    }
};

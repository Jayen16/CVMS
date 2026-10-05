<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->json('proof_uploaders')->nullable()->after('proof_paths');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->dropColumn('proof_uploaders');
        });
    }
};

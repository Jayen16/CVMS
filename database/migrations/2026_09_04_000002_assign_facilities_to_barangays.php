<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table): void {
            $table->foreignUuid('barangay_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->index('barangay_id');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};

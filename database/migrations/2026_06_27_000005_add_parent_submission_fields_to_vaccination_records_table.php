<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->string('source')->default('barangay_clinic')->after('recorded_by');
            $table->string('verification_status')->default('verified')->after('source');
            $table->foreignUuid('submitted_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->foreignUuid('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('clinic_name')->nullable()->after('administered_at');
            $table->string('clinic_location')->nullable()->after('clinic_name');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'source',
                'verification_status',
                'verified_at',
                'clinic_name',
                'clinic_location',
            ]);
        });
    }
};

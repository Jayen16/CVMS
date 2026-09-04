<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_activation_codes', function (Blueprint $table): void {
            $table->foreignUuid('designated_user_id')->nullable()->after('facility_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('system_installations', function (Blueprint $table): void {
            $table->string('setup_user_name')->nullable()->after('barangay_id');
            $table->string('setup_user_email')->nullable()->after('setup_user_name');
        });
    }

    public function down(): void
    {
        Schema::table('system_installations', function (Blueprint $table): void {
            $table->dropColumn(['setup_user_name', 'setup_user_email']);
        });
        Schema::table('facility_activation_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('designated_user_id');
        });
    }
};

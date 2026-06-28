<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('barangay_id');
            $table->timestamp('invitation_accepted_at')->nullable()->after('is_active');
        });

        DB::table('users')->where('role', '!=', 'nurse')->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'invitation_accepted_at']);
        });
    }
};

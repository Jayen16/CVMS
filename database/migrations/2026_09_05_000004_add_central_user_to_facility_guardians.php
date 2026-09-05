<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_guardians', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->nullable()->after('guardian_uuid')->constrained('users')->nullOnDelete();
            $table->timestamp('invitation_sent_at')->nullable()->after('sync_version');
        });
    }

    public function down(): void
    {
        Schema::table('facility_guardians', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('invitation_sent_at');
        });
    }
};

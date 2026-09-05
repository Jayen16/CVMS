<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('privacy_notice_version')->nullable()->after('invitation_accepted_at');
            $table->timestamp('privacy_acknowledged_at')->nullable()->after('privacy_notice_version');
            $table->ipAddress('privacy_acknowledged_ip')->nullable()->after('privacy_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['privacy_notice_version', 'privacy_acknowledged_at', 'privacy_acknowledged_ip']);
        });
    }
};

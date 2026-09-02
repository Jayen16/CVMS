<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_statuses', function (Blueprint $table): void {
            $table->string('state')->default('idle')->after('scope');
            $table->text('last_error')->nullable()->after('last_failed');
            $table->timestamp('last_attempted_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('sync_statuses', function (Blueprint $table): void {
            $table->dropColumn(['state', 'last_error', 'last_attempted_at']);
        });
    }
};

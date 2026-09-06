<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_statuses', function (Blueprint $table): void {
            $table->unsignedInteger('last_pulled')->default(0)->after('last_processed');
            $table->uuid('last_pull_batch_uuid')->nullable()->after('last_pulled');
        });
    }

    public function down(): void
    {
        Schema::table('sync_statuses', function (Blueprint $table): void {
            $table->dropColumn(['last_pulled', 'last_pull_batch_uuid']);
        });
    }
};

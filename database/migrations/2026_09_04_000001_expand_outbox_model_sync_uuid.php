<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_sync_outbox', function (Blueprint $table): void {
            $table->string('model_sync_uuid', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('offline_sync_outbox', function (Blueprint $table): void {
            $table->uuid('model_sync_uuid')->nullable()->change();
        });
    }
};

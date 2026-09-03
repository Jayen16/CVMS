<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccine_inventory_transactions', function (Blueprint $table): void {
            $table->uuid('sync_uuid')->nullable()->unique();
            $table->unsignedInteger('sync_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('vaccine_inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique(['sync_uuid']);
            $table->dropColumn(['sync_uuid', 'sync_version']);
        });
    }
};

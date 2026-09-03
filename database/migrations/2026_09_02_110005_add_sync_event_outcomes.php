<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_processed_events', function (Blueprint $table): void {
            $table->string('operation')->after('record_uuid');
            $table->unsignedInteger('version')->after('operation');
            $table->string('outcome')->default('applied')->after('version');
            $table->timestamp('applied_at')->nullable()->after('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('sync_processed_events', function (Blueprint $table): void {
            $table->dropColumn(['operation', 'version', 'outcome', 'applied_at']);
        });
    }
};

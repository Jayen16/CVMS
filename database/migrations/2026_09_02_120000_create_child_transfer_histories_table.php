<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_transfer_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('child_sync_uuid')->index();
            $table->uuid('facility_uuid')->nullable()->index();
            $table->string('from_barangay_name');
            $table->string('to_barangay_name');
            $table->string('municipality_code')->nullable();
            $table->uuid('transferred_by_uuid')->nullable();
            $table->string('transferred_by_name')->nullable();
            $table->string('transferred_by_role')->nullable();
            $table->timestamp('transferred_at');
            $table->text('reason')->nullable();
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamps();
            $table->unique(['child_sync_uuid', 'transferred_at'], 'child_transfer_history_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_transfer_histories');
    }
};

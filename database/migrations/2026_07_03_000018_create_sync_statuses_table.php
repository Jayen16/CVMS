<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope')->unique();
            $table->foreignUuid('last_synced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('last_processed')->default(0);
            $table->unsignedInteger('last_failed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_statuses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_received_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity');
            $table->string('record_uuid');
            $table->string('operation')->default('received');
            $table->json('payload')->nullable();
            $table->uuid('last_received_batch_uuid');
            $table->timestamp('first_received_at');
            $table->timestamp('last_received_at');
            $table->timestamps();
            $table->unique(['entity', 'record_uuid']);
            $table->index('last_received_batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_received_items');
    }
};

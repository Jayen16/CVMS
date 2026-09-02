<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('item_code')->unique();
            $table->foreignUuid('barangay_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccine_type_id')->constrained()->restrictOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_at');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'vaccine_type_id']);
        });

        Schema::table('vaccine_inventory_transactions', function (Blueprint $table): void {
            $table->foreignUuid('vaccine_inventory_item_id')
                ->nullable()
                ->after('vaccine_type_id')
                ->constrained('vaccine_inventory_items')
                ->restrictOnDelete();
            $table->foreignUuid('vaccination_record_id')
                ->nullable()
                ->after('vaccine_inventory_item_id')
                ->constrained('vaccination_records')
                ->restrictOnDelete();
            $table->unique('vaccination_record_id');
        });
    }

    public function down(): void
    {
        Schema::table('vaccine_inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign(['vaccine_inventory_item_id']);
            $table->dropForeign(['vaccination_record_id']);
            $table->dropUnique(['vaccination_record_id']);
            $table->dropColumn('vaccination_record_id');
            $table->dropColumn('vaccine_inventory_item_id');
        });

        Schema::dropIfExists('vaccine_inventory_items');
    }
};

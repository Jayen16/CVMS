<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barangay_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vaccine_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('transaction_type');
            $table->string('movement');
            $table->unsignedInteger('quantity');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('transaction_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['barangay_id', 'vaccine_type_id', 'transaction_date'],
                'inventory_barangay_vaccine_date_idx'
            );
            $table->index(['transaction_type', 'movement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_inventory_transactions');
    }
};

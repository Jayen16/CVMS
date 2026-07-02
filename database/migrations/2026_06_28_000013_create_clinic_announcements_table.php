<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default('schedule');
            $table->string('audience')->default('all');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('location')->nullable();
            $table->text('message');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_announcements');
    }
};

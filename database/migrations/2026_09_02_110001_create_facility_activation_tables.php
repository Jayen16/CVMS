<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('facility_activation_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'expires_at', 'used_at']);
        });
        Schema::create('facility_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained()->cascadeOnDelete();
            $table->uuid('instance_uuid')->unique();
            $table->string('instance_name')->nullable();
            $table->uuid('passport_client_id')->unique();
            $table->string('status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('system_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('instance_uuid')->unique();
            $table->uuid('facility_id')->nullable();
            $table->string('facility_code')->nullable();
            $table->string('facility_name')->nullable();
            $table->text('central_url')->nullable();
            $table->uuid('passport_client_id')->nullable();
            $table->text('passport_client_secret')->nullable();
            $table->string('status')->default('unactivated');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->text('pull_cursor')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_installations');
        Schema::dropIfExists('facility_connections');
        Schema::dropIfExists('facility_activation_codes');
        Schema::dropIfExists('facilities');
    }
};

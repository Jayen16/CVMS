<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('guardian_uuid');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamps();
            $table->unique(['facility_id', 'guardian_uuid'], 'fg_facility_guardian_uq');
        });

        Schema::create('facility_child_guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('child_uuid');
            $table->uuid('guardian_uuid');
            $table->string('relationship');
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamps();
            $table->unique(['facility_id', 'child_uuid', 'guardian_uuid'], 'fcg_facility_child_guardian_uq');
        });

        Schema::create('facility_inventory_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('transaction_uuid');
            $table->string('barangay_name')->nullable();
            $table->string('vaccine_code')->nullable();
            $table->string('item_code')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('transaction_type');
            $table->string('movement', 8);
            $table->unsignedInteger('quantity');
            $table->date('transaction_date');
            $table->string('reference_number')->nullable();
            $table->string('recorded_by_uuid')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->string('recorded_by_role')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamps();
            $table->unique(['facility_id', 'transaction_uuid'], 'fit_facility_transaction_uq');
        });

        Schema::create('child_appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id')->nullable()->index();
            $table->uuid('vaccine_type_id')->nullable();
            $table->uuid('facility_uuid')->nullable()->index();
            $table->dateTime('scheduled_for');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('created_by_role')->nullable();
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamps();
        });

        Schema::create('parent_change_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('request_uuid');
            $table->uuid('child_uuid');
            $table->uuid('parent_uuid')->nullable();
            $table->string('request_type');
            $table->json('requested_data')->nullable();
            $table->string('status')->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'request_uuid'], 'pcr_facility_request_uq');
        });

        Schema::create('facility_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('event_uuid');
            $table->string('event', 40);
            $table->string('auditable_type');
            $table->string('auditable_id')->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('actor_uuid')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'event_uuid'], 'fae_facility_event_uq');
        });

        Schema::create('facility_notification_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id')->index();
            $table->uuid('notification_uuid');
            $table->string('recipient_uuid');
            $table->string('notification_type');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['facility_id', 'notification_uuid'], 'fnr_facility_notification_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_change_requests');
        Schema::dropIfExists('child_appointments');
        Schema::dropIfExists('facility_inventory_transactions');
        Schema::dropIfExists('facility_child_guardians');
        Schema::dropIfExists('facility_guardians');
        Schema::dropIfExists('facility_notification_requests');
        Schema::dropIfExists('facility_audit_events');
    }
};

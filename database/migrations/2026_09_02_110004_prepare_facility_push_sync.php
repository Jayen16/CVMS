<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_profiles', function (Blueprint $table): void {
            $table->uuid('facility_uuid')->nullable()->index()->after('sync_uuid');
            $table->uuid('registered_by_uuid')->nullable()->after('facility_uuid');
            $table->string('registered_by_name')->nullable()->after('registered_by_uuid');
            $table->string('registered_by_role')->nullable()->after('registered_by_name');
            $table->unsignedInteger('sync_version')->default(1)->after('registered_by_role');
            $table->foreignUuid('created_by')->nullable()->change();
        });

        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->uuid('facility_uuid')->nullable()->index()->after('sync_uuid');
            $table->uuid('administered_by_uuid')->nullable()->after('facility_uuid');
            $table->uuid('recorded_by_uuid')->nullable()->after('administered_by_uuid');
            $table->string('administered_by_name')->nullable()->after('recorded_by_uuid');
            $table->string('recorded_by_name')->nullable()->after('administered_by_name');
            $table->string('recorded_by_role')->nullable()->after('recorded_by_name');
            $table->unsignedInteger('sync_version')->default(1)->after('recorded_by_role');
            $table->foreignUuid('recorded_by')->nullable()->change();
        });

        Schema::create('facility_staff', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id');
            $table->uuid('staff_uuid');
            $table->string('name');
            $table->string('role');
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'staff_uuid']);
            $table->index('facility_id');
        });

        Schema::create('sync_processed_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_id');
            $table->uuid('event_uuid')->unique();
            $table->string('entity');
            $table->string('record_uuid');
            $table->timestamps();
        });

        Schema::table('offline_sync_outbox', function (Blueprint $table): void {
            $table->uuid('event_uuid')->nullable()->unique()->after('id');
            $table->string('entity')->nullable()->after('model_type');
            $table->unsignedInteger('version')->default(1)->after('operation');
            $table->string('status')->default('pending')->after('version');
            $table->timestamp('last_attempted_at')->nullable()->after('last_error');
            $table->timestamp('synchronized_at')->nullable()->after('synced_at');
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_processed_events');
        Schema::dropIfExists('facility_staff');
        Schema::table('offline_sync_outbox', function (Blueprint $table): void {
            $table->dropIndex(['status', 'queued_at']);
            $table->dropUnique(['event_uuid']);
            $table->dropColumn(['event_uuid', 'entity', 'version', 'status', 'last_attempted_at', 'synchronized_at']);
        });
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->dropColumn(['facility_uuid', 'administered_by_uuid', 'recorded_by_uuid', 'administered_by_name', 'recorded_by_name', 'recorded_by_role', 'sync_version']);
        });
        Schema::table('child_profiles', function (Blueprint $table): void {
            $table->dropColumn(['facility_uuid', 'registered_by_uuid', 'registered_by_name', 'registered_by_role', 'sync_version']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->uuid('sync_uuid')->nullable()->unique()->after('vaccine_card_token');
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->uuid('sync_uuid')->nullable()->unique()->after('client_submission_id');
        });

        Schema::table('clinic_announcements', function (Blueprint $table) {
            $table->uuid('sync_uuid')->nullable()->unique()->after('active');
        });

        Schema::table('adverse_event_reports', function (Blueprint $table) {
            $table->uuid('sync_uuid')->nullable()->unique()->after('notes');
        });

        foreach (['child_profiles', 'vaccination_records', 'clinic_announcements', 'adverse_event_reports'] as $table) {
            DB::table($table)->whereNull('sync_uuid')->get(['id'])->each(function ($row) use ($table): void {
                DB::table($table)->where('id', $row->id)->update(['sync_uuid' => (string) Str::uuid()]);
            });
        }

        Schema::create('offline_sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->uuid('model_sync_uuid')->nullable();
            $table->string('operation')->default('upsert');
            $table->json('payload');
            $table->timestamp('queued_at');
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['synced_at', 'queued_at']);
            $table->index(['model_type', 'model_sync_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_outbox');

        Schema::table('adverse_event_reports', function (Blueprint $table) {
            $table->dropUnique(['sync_uuid']);
            $table->dropColumn('sync_uuid');
        });

        Schema::table('clinic_announcements', function (Blueprint $table) {
            $table->dropUnique(['sync_uuid']);
            $table->dropColumn('sync_uuid');
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->dropUnique(['sync_uuid']);
            $table->dropColumn('sync_uuid');
        });

        Schema::table('child_profiles', function (Blueprint $table) {
            $table->dropUnique(['sync_uuid']);
            $table->dropColumn('sync_uuid');
        });
    }
};

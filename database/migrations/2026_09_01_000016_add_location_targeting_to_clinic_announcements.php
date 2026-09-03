<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('clinic_announcements', 'region_id')) {
                $table->foreignUuid('region_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('clinic_announcements', 'province_id')) {
                $table->foreignUuid('province_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('clinic_announcements', 'municipality_id')) {
                $table->foreignUuid('municipality_id')->nullable()->after('province_id')->constrained()->nullOnDelete();
            }
        });

        $indexExists = collect(Schema::getIndexes('clinic_announcements'))->contains(
            fn (array $index): bool => $index['name'] === 'clinic_announcements_location_index'
                || $index['columns'] === ['region_id', 'province_id', 'municipality_id', 'barangay_id'],
        );

        if (! $indexExists) {
            Schema::table('clinic_announcements', function (Blueprint $table): void {
                $table->index(['region_id', 'province_id', 'municipality_id', 'barangay_id'], 'clinic_announcements_location_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clinic_announcements', function (Blueprint $table): void {
            if (Schema::hasColumn('clinic_announcements', 'region_id')) {
                $table->dropColumn(['region_id', 'province_id', 'municipality_id']);
            }
        });
    }
};

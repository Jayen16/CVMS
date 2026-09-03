<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_profiles', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('sync_uuid')->index();
            $table->foreignUuid('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 100)->nullable()->after('archived_by');
        });
    }

    public function down(): void
    {
        Schema::table('child_profiles', function (Blueprint $table): void {
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });
    }
};

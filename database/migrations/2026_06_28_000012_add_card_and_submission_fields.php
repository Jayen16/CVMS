<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->string('vaccine_card_token')->nullable()->unique()->after('address');
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('clinic_location');
            $table->string('client_submission_id')->nullable()->after('proof_path');
            $table->unique('client_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->dropUnique(['client_submission_id']);
            $table->dropColumn(['proof_path', 'client_submission_id']);
        });

        Schema::table('child_profiles', function (Blueprint $table) {
            $table->dropUnique(['vaccine_card_token']);
            $table->dropColumn('vaccine_card_token');
        });
    }
};

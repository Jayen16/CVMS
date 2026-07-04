<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->json('proof_paths')->nullable()->after('proof_path');
        });

        DB::table('vaccination_records')
            ->select(['id', 'proof_path'])
            ->whereNotNull('proof_path')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $record): void {
                DB::table('vaccination_records')
                    ->where('id', $record->id)
                    ->update([
                        'proof_paths' => json_encode([$record->proof_path], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table): void {
            $table->dropColumn('proof_paths');
        });
    }
};

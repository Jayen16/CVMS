<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
            $table->unique('name');
        });
        Schema::create('provinces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
            $table->unique(['region_id', 'name']);
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->timestamps();
            $table->unique(['province_id', 'name']);
        });
        Schema::table('barangays', function (Blueprint $table) {
            $table->foreignUuid('municipality_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('municipality_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('municipality_id')->nullable()->after('barangay_id')->constrained()->nullOnDelete();
            $table->index('municipality_id');
        });
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('municipality_id'));
        Schema::table('barangays', fn (Blueprint $table) => $table->dropConstrainedForeignId('municipality_id'));
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};

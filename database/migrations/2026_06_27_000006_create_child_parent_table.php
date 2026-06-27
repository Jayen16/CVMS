<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_parent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->default('parent');
            $table->timestamps();

            $table->unique(['child_profile_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_parent');
    }
};

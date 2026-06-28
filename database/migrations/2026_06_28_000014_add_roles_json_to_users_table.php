<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role');
        });

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $role = $user->role === 'admin' ? 'superadmin' : $user->role;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'role' => $role,
                        'roles' => json_encode([$role], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->select(['id', 'role', 'roles'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $roles = json_decode($user->roles ?? '[]', true);
                $primaryRole = is_array($roles) && count($roles) > 0 ? $roles[0] : $user->role;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'role' => $primaryRole === 'superadmin' ? 'admin' : $primaryRole,
                    ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};

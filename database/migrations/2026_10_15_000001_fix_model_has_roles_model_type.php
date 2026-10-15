<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize any double-escaped or corrupted model_type strings in model_has_roles
        DB::table('model_has_roles')
            ->where('model_type', 'App\\\\Models\\\\User')
            ->orWhere('model_type', 'like', '%\\\\%')
            ->update(['model_type' => User::class]);

        // Normalize model_has_permissions
        DB::table('model_has_permissions')
            ->where('model_type', 'App\\\\Models\\\\User')
            ->orWhere('model_type', 'like', '%\\\\%')
            ->update(['model_type' => User::class]);

        // Ensure default Staff user (staff@tasa.com) has Staff role assigned if present
        $staffUser = User::where('email', 'staff@tasa.com')->first();
        if ($staffUser && ! $staffUser->hasRole('Staff')) {
            $staffUser->assignSingleRole('Staff');
        }

        // Forget cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};

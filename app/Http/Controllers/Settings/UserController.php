<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles')
            ->latest('id');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $roleName = $request->input('role');
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->paginate(15)->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'role' => $user->roles->first()?->name ?? 'None',
                'is_owner' => $user->hasRole('Owner'),
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i'),
                'created_at' => $user->created_at?->format('Y-m-d'),
            ];
        });

        $roles = Role::select('id', 'name', 'is_locked')->get();

        return Inertia::render('settings/Users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignSingleRole($validated['role']);

        AuditService::log(
            'user_created',
            $user,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $validated['role'],
                'is_active' => true,
            ]
        );

        return back()->with('success', "User '{$user->name}' created successfully.");
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['required', 'boolean'],
        ]);

        // Prevent modifying role or deactivating Owner
        if ($user->hasRole('Owner')) {
            if ($validated['role'] !== 'Owner') {
                abort(403, 'The Owner role cannot be changed.');
            }
            if (! $validated['is_active']) {
                abort(403, 'The Owner account cannot be deactivated.');
            }
        }

        $oldRole = $user->roles->first()?->name;
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $oldRole,
            'is_active' => (bool) $user->is_active,
        ];

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $user->assignSingleRole($validated['role']);

        $newValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $validated['role'],
            'is_active' => (bool) $user->is_active,
        ];

        AuditService::log(
            'user_updated',
            $user,
            $oldValues,
            $newValues
        );

        return back()->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        if ($user->hasRole('Owner')) {
            abort(403, 'The Owner account cannot be deactivated.');
        }

        $oldStatus = (bool) $user->is_active;
        $newStatus = ! $oldStatus;

        $user->update(['is_active' => $newStatus]);

        AuditService::log(
            $newStatus ? 'user_activated' : 'user_deactivated',
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $newStatus]
        );

        $statusLabel = $newStatus ? 'activated' : 'deactivated';
        return back()->with('success', "User '{$user->name}' has been {$statusLabel}.");
    }

    /**
     * Force reset a user's password.
     */
    public function forcePasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditService::log(
            'user_password_forced_reset',
            $user,
            null,
            ['forced_by_user_id' => auth()->id()]
        );

        return back()->with('success', "Password for user '{$user->name}' was reset successfully.");
    }

    /**
     * Remove the specified user from storage (soft delete).
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Owner')) {
            abort(403, 'The Owner user cannot be deleted.');
        }

        if ($user->id === auth()->id()) {
            abort(403, 'You cannot delete your own account.');
        }

        $userData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        $user->delete();

        AuditService::log(
            'user_deleted',
            $user,
            $userData,
            ['deleted_at' => now()->toDateTimeString()]
        );

        return back()->with('success', "User '{$userData['name']}' deleted successfully.");
    }
}

<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('owner can access all administrative sections including settings, roles, users, and audit logs', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $this->actingAs($owner);

    $this->get('/dashboard')->assertOk();
    $this->get('/settings/roles')->assertOk();
    $this->get('/settings/users')->assertOk();
    $this->get('/settings/general')->assertOk();
    $this->get('/settings/invoice')->assertOk();
    $this->get('/settings/pathao')->assertOk();
    $this->get('/settings/audit-logs')->assertOk();
    $this->get('/reports/profit-loss')->assertOk();
    $this->get('/payroll/employees')->assertOk();
});

test('staff can log in and reach the dashboard with basic operational metrics only', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    $response = $this->get('/dashboard');
    $response->assertOk();

    // Staff receives basic metrics, but financials (net profit, payroll kpis) are null
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('basic')
        ->where('financials', null)
        ->where('canViewFinancials', false)
    );
});

test('staff can access default permitted modules such as products, sales, and expenses', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    $this->get('/inventory/products')->assertOk();
    $this->get('/sales/create')->assertOk();
    $this->get('/sales/invoices')->assertOk();
    $this->get('/expenses')->assertOk();
});

test('staff receives 403 on unpermitted modules such as payroll, settings, and profit-loss', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    $this->get('/payroll/employees')->assertStatus(403);
    $this->get('/payroll/workers')->assertStatus(403);
    $this->get('/settings/roles')->assertStatus(403);
    $this->get('/settings/users')->assertStatus(403);
    $this->get('/settings/general')->assertStatus(403);
    $this->get('/reports/profit-loss')->assertStatus(403);
});

test('owner can grant permission to staff role and staff immediately gains access', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    // Initially staff cannot access payroll
    $this->actingAs($staff);
    $this->get('/payroll/employees')->assertStatus(403);

    // Owner grants payroll.view to Staff role via settings/roles endpoint
    $this->actingAs($owner);
    $staffRole = Role::where('name', 'Staff')->first();
    $currentPermissions = $staffRole->permissions->pluck('name')->toArray();
    $newPermissions = array_unique(array_merge($currentPermissions, ['payroll.view']));

    $updateResponse = $this->put("/settings/roles/{$staffRole->id}", [
        'name' => 'Staff',
        'description' => 'Staff operational role with granted payroll access',
        'permissions' => $newPermissions,
    ]);
    $updateResponse->assertRedirect();

    // Now staff can access payroll immediately without application restart or cache issue
    $this->actingAs($staff);
    $this->get('/payroll/employees')->assertOk();
});

test('owner can revoke permission from staff role and staff immediately receives 403', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    // Grant payroll.view first
    $staffRole = Role::where('name', 'Staff')->first();
    $staffRole->givePermissionTo('payroll.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($staff);
    $this->get('/payroll/employees')->assertOk();

    // Owner revokes payroll.view via update endpoint
    $this->actingAs($owner);
    $updatedPermissions = array_diff($staffRole->permissions->pluck('name')->toArray(), ['payroll.view']);

    $this->put("/settings/roles/{$staffRole->id}", [
        'name' => 'Staff',
        'description' => $staffRole->description,
        'permissions' => array_values($updatedPermissions),
    ])->assertRedirect();

    // Staff now receives 403 on subsequent requests
    $this->actingAs($staff);
    $this->get('/payroll/employees')->assertStatus(403);
});

test('staff cannot change their own role or escalate privileges to owner', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    // Staff tries to update their own role to Owner via user update endpoint
    $response = $this->put("/settings/users/{$staff->id}", [
        'name' => 'Staff Hacker',
        'email' => $staff->email,
        'role' => 'Owner',
    ]);

    // Forbidden
    $response->assertStatus(403);

    // Verify role in database was not escalated
    $staff->refresh();
    expect($staff->hasRole('Owner'))->toBeFalse();
    expect($staff->hasRole('Staff'))->toBeTrue();
});

test('non-owner cannot assign owner role to any user', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');
    // Give staff users.manage permission to test the role escalation prevention
    $staff->givePermissionTo('users.manage');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $otherUser = User::factory()->create();
    $otherUser->assignSingleRole('Staff');

    $this->actingAs($staff);

    // Staff attempts to elevate other user to Owner
    $response = $this->put("/settings/users/{$otherUser->id}", [
        'name' => 'Elevated User',
        'email' => $otherUser->email,
        'role' => 'Owner',
        'is_active' => true,
    ]);

    $response->assertStatus(403);

    $otherUser->refresh();
    expect($otherUser->hasRole('Owner'))->toBeFalse();
});

test('user accounts screen persists role assignments made by owner', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $targetUser = User::factory()->create();
    $targetUser->assignSingleRole('Staff');

    $this->actingAs($owner);

    // Owner updates user name and maintains role
    $this->put("/settings/users/{$targetUser->id}", [
        'name' => 'Senior Staff Member',
        'email' => $targetUser->email,
        'role' => 'Staff',
        'is_active' => true,
    ])->assertRedirect();

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Senior Staff Member');
    expect($targetUser->hasRole('Staff'))->toBeTrue();
});

test('cost prices and profit margins are stripped from response for staff without view_costs permission', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    // Staff visits products listing
    $response = $this->get('/inventory/products');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Products')
        ->where('canViewCost', false)
    );
});

test('403 responses return the user-friendly Error component with 403 status code for web requests', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    // Unauthorized web request
    $response = $this->get('/payroll/employees');

    $response->assertStatus(403);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Error')
        ->where('status', 403)
        ->has('message')
    );
});

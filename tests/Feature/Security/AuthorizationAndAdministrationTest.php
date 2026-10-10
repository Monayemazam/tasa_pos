<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Seed roles, permissions, and settings
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

test('unauthenticated users are redirected to login on protected routes', function () {
    $protectedUrls = [
        '/dashboard',
        '/settings/users',
        '/settings/roles',
        '/settings/general',
        '/settings/invoice',
        '/settings/pathao',
        '/settings/audit-logs',
        '/payroll/employees',
        '/reports/profit-loss',
    ];

    foreach ($protectedUrls as $url) {
        $response = $this->get($url);
        $response->assertRedirect('/login');
    }
});

test('public self-registration is disabled', function () {
    $response = $this->post('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]);

    $response->assertNotFound();
});

test('inactive users are blocked from logging in with deactivated account message', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive@tasa.com',
        'password' => Hash::make('secret123'),
        'is_active' => false,
    ]);
    $inactiveUser->assignSingleRole('Staff');

    $response = $this->post('/login', [
        'email' => 'inactive@tasa.com',
        'password' => 'secret123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('active user deactivated mid-session is aborted with 403 on next request', function () {
    $user = User::factory()->create([
        'email' => 'active-mid@tasa.com',
        'is_active' => true,
    ]);
    $user->assignSingleRole('Staff');

    $this->actingAs($user);

    // Initial request succeeds
    $this->get('/dashboard')->assertOk();

    // Deactivate user in database mid-session
    $user->update(['is_active' => false]);

    // Next request is terminated immediately with 403
    $response = $this->get('/dashboard');
    $response->assertStatus(403);
    $this->assertGuest();
});

test('locked owner role cannot be renamed, edited, or deleted', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $ownerRole = Role::where('name', 'Owner')->first();
    expect($ownerRole->is_locked)->toBeTrue();

    $this->actingAs($owner);

    // Attempt to update Owner role
    $updateResponse = $this->put("/settings/roles/{$ownerRole->id}", [
        'name' => 'Renamed Owner',
        'permissions' => ['dashboard.view'],
    ]);
    $updateResponse->assertStatus(403);

    // Attempt to delete Owner role
    $deleteResponse = $this->delete("/settings/roles/{$ownerRole->id}");
    $deleteResponse->assertStatus(403);
});

test('owner user cannot be deactivated or deleted by anyone', function () {
    $owner = User::factory()->create(['name' => 'Primary Owner']);
    $owner->assignSingleRole('Owner');

    $staff = User::factory()->create(['name' => 'Staff Member']);
    $staff->assignSingleRole('Staff');

    // 1. Staff attempts to deactivate Owner -> 403
    $this->actingAs($staff);
    $this->post("/settings/users/{$owner->id}/toggle-active")->assertStatus(403);
    $this->delete("/settings/users/{$owner->id}")->assertStatus(403);

    // 2. Even Owner themselves cannot deactivate or delete the Owner account -> 403
    $this->actingAs($owner);
    $this->post("/settings/users/{$owner->id}/toggle-active")->assertStatus(403);
    $this->delete("/settings/users/{$owner->id}")->assertStatus(403);
});

test('owner has full access to all system administration and reporting modules', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $this->actingAs($owner);

    $this->get('/settings/users')->assertOk();
    $this->get('/settings/roles')->assertOk();
    $this->get('/settings/general')->assertOk();
    $this->get('/settings/invoice')->assertOk();
    $this->get('/settings/pathao')->assertOk();
    $this->get('/settings/audit-logs')->assertOk();
    $this->get('/payroll/employees')->assertOk();
    $this->get('/reports/profit-loss')->assertOk();
});

test('staff user receives 403 on payroll, p&l, and settings by direct url', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    // Staff lacks payroll.view -> 403
    $this->get('/payroll/employees')->assertStatus(403);
    $this->get('/payroll/workers')->assertStatus(403);

    // Staff lacks reports.profit_loss -> 403
    $this->get('/reports/profit-loss')->assertStatus(403);

    // Staff lacks settings.manage / users.manage / roles.manage -> 403
    $this->get('/settings/users')->assertStatus(403);
    $this->get('/settings/roles')->assertStatus(403);
    $this->get('/settings/general')->assertStatus(403);
    $this->get('/settings/invoice')->assertStatus(403);
    $this->get('/settings/pathao')->assertStatus(403);
    $this->get('/settings/audit-logs')->assertStatus(403);
});

test('role permission changes take effect on the next request', function () {
    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $this->actingAs($staff);

    // 1. Staff initially cannot view payroll
    $this->get('/payroll/employees')->assertStatus(403);

    // 2. Grant payroll.view to Staff role
    $staffRole = Role::where('name', 'Staff')->first();
    $staffRole->givePermissionTo('payroll.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // 3. Next request by Staff immediately succeeds with 200
    $this->get('/payroll/employees')->assertOk();

    // 4. Revoke payroll.view from Staff role
    $staffRole->revokePermissionTo('payroll.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // 5. Subsequent request is immediately denied with 403
    $this->get('/payroll/employees')->assertStatus(403);
});

test('sensitive financial columns are redacted for staff but visible to owner', function () {
    $middleware = new \App\Http\Middleware\ProtectSensitiveFinancials();

    $staff = User::factory()->create();
    $staff->assignSingleRole('Staff');

    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $payload = [
        'id' => 101,
        'name' => 'Leather Wallet',
        'selling_price' => '1500.00',
        'cost_price' => '800.00',
        'unit_cost' => '750.00',
        'gross_profit' => '700.00',
        'margin' => '46.67',
    ];

    // Request as Staff (should be stripped)
    $staffRequest = Request::create('/api/products/101', 'GET');
    $staffRequest->setUserResolver(fn () => $staff);

    $staffResponse = $middleware->handle($staffRequest, function () use ($payload) {
        return new JsonResponse($payload);
    });

    $staffData = json_decode($staffResponse->getContent(), true);
    expect($staffData)->toHaveKey('name')
        ->toHaveKey('selling_price')
        ->not->toHaveKey('cost_price')
        ->not->toHaveKey('unit_cost')
        ->not->toHaveKey('gross_profit')
        ->not->toHaveKey('margin');

    // Request as Owner (should retain all fields)
    $ownerRequest = Request::create('/api/products/101', 'GET');
    $ownerRequest->setUserResolver(fn () => $owner);

    $ownerResponse = $middleware->handle($ownerRequest, function () use ($payload) {
        return new JsonResponse($payload);
    });

    $ownerData = json_decode($ownerResponse->getContent(), true);
    expect($ownerData)->toHaveKey('cost_price')
        ->toHaveKey('unit_cost')
        ->toHaveKey('gross_profit')
        ->toHaveKey('margin')
        ->and($ownerData['cost_price'])->toBe('800.00');
});

test('user management enforces single role and logs audit events', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $this->actingAs($owner);

    // Create a new user
    $response = $this->post('/settings/users', [
        'name' => 'Sohail Khan',
        'email' => 'sohail@tasa.com',
        'phone' => '01712345678',
        'password' => 'Password123!',
        'role' => 'Staff',
    ]);

    $response->assertRedirect();

    $newUser = User::where('email', 'sohail@tasa.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->roles)->toHaveCount(1)
        ->and($newUser->roles->first()->name)->toBe('Staff');

    // Verify audit log record was created
    $audit = AuditLog::where('event', 'user_created')
        ->where('auditable_id', $newUser->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values['name'])->toBe('Sohail Khan')
        ->and($audit->new_values['role'])->toBe('Staff');
});

test('audit logs are strictly append-only and cannot be updated or deleted', function () {
    $log = AuditService::log(
        'test_event',
        User::class,
        null,
        ['foo' => 'bar'],
        null
    );

    expect($log->id)->toBeGreaterThan(0);

    // Attempting to update throws RuntimeException
    expect(function () use ($log) {
        $log->update(['event' => 'tampered_event']);
    })->toThrow(RuntimeException::class, 'Audit logs are append-only and cannot be updated.');

    // Attempting to delete throws RuntimeException
    expect(function () use ($log) {
        $log->delete();
    })->toThrow(RuntimeException::class, 'Audit logs are append-only and cannot be deleted.');
});

test('custom invoice template rejects executable php and script injections', function () {
    $owner = User::factory()->create();
    $owner->assignSingleRole('Owner');

    $this->actingAs($owner);

    // Attempt with PHP tag
    $phpResponse = $this->post('/settings/invoice', [
        'prefix' => 'TASA',
        'paper_size' => 'A4',
        'custom_template' => '<div><?php phpinfo(); ?></div>',
    ]);
    $phpResponse->assertSessionHasErrors('custom_template');

    // Attempt with script tag
    $scriptResponse = $this->post('/settings/invoice', [
        'prefix' => 'TASA',
        'paper_size' => 'A4',
        'custom_template' => '<div><script>alert("xss")</script></div>',
    ]);
    $scriptResponse->assertSessionHasErrors('custom_template');

    // Valid template with safe tokens passes
    $validResponse = $this->post('/settings/invoice', [
        'prefix' => 'TASA',
        'paper_size' => 'A4',
        'custom_template' => '<div>{{invoice_no}} - {{customer_name}}</div>',
    ]);
    $validResponse->assertSessionHasNoErrors();
    expect(Setting::get('invoice.custom_template'))->toContain('{{invoice_no}}');
});

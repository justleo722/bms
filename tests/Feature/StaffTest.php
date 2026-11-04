<?php

use App\Models\StaffRole;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'administrator', 'status' => 'active']));
});

it('creates database-backed staff with a unique sign-in code and counter assignment', function () {
    $role = StaffRole::query()->where('slug', 'cashier')->firstOrFail();

    $response = $this->postJson('/staff', [
        'name' => 'Taylor Staff',
        'phone' => '+255 700 111 222',
        'role' => $role->slug,
        'status' => 'active',
    ])->assertCreated()
        ->assertJsonPath('staff.name', 'Taylor Staff')
        ->assertJsonPath('staff.role', 'cashier')
        ->assertJsonPath('staff.counterId', null)
        ->assertJsonPath('staff.counter', 'All Counters');

    $staff = User::query()->findOrFail($response->json('staff.id'));
    expect($staff->pin)->toMatch('/^\d{4}$/')
        ->and($staff->permissions)->toBe([])
        ->and($staff->status)->toBe('active')
        ->and($staff->counter_id)->toBeNull();

    $this->get('/staff')
        ->assertOk()
        ->assertSee('Taylor Staff')
        ->assertSee('(All)')
        ->assertDontSee('John Mwaipopo');

    $this->post('/logout')->assertRedirect(route('login'));
    $this->post('/login', ['pin' => $staff->pin])->assertRedirect('/no-access');
    expect(auth()->id())->toBe($staff->id);
});

it('sends attendants to an enabled module and only renders enabled modules in the bottom navigation', function () {
    $permissions = ['pos' => ['enabled' => true, 'components' => ['sell' => true, 'printDocket' => false]]];
    $staff = User::factory()->create([
        'name' => 'POS Attendant',
        'role' => 'cashier',
        'pin' => '6824',
        'status' => 'active',
        'permissions' => $permissions,
    ]);

    $this->post('/logout')->assertRedirect(route('login'));
    $this->post('/login', ['pin' => $staff->pin])->assertRedirect('/pos');
    $this->get('/')->assertRedirect('/pos');

    $html = $this->get('/pos')->assertOk()->getContent();
    $bottomNavigation = substr($html, strpos($html, '<nav class="fixed inset-x-0 bottom-0'));
    expect($bottomNavigation)
        ->toContain('href="/pos"')
        ->not->toContain('href="/sales"')
        ->not->toContain('href="/inventory"');
});

it('shows a helpful page rather than a forbidden error when no module is enabled', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'pin' => '6825',
        'status' => 'active',
        'permissions' => [],
    ]);

    $this->post('/logout')->assertRedirect(route('login'));
    $this->post('/login', ['pin' => $staff->pin])->assertRedirect('/no-access');
    $this->get('/no-access')->assertOk()->assertSee('No modules enabled');
});

it('persists roles and retains assigned roles in trash until staff references are removed', function () {
    $this->postJson('/settings/staff-roles', ['name' => 'Administrator'])->assertUnprocessable();
    $response = $this->postJson('/settings/staff-roles', ['name' => 'Floor Supervisor'])
        ->assertCreated()
        ->assertJsonPath('role.label', 'Floor Supervisor');
    $role = StaffRole::query()->findOrFail($response->json('role.recordId'));

    $staffResponse = $this->postJson('/staff', [
        'name' => 'Role Holder',
        'role' => $role->slug,
        'status' => 'active',
    ])->assertCreated();
    $staffId = $staffResponse->json('staff.id');

    $this->deleteJson('/settings/staff-roles/'.$role->id)->assertOk();
    $this->deleteJson('/settings/staff-roles/'.$role->id.'/permanent')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This role is assigned to saved staff accounts and cannot be permanently deleted.');
    $this->get('/settings')
        ->assertOk()
        ->assertSee('server.staff-roles')
        ->assertSee('server.staff');

    $this->deleteJson('/staff/'.$staffId)->assertOk();
    $this->deleteJson('/staff/'.$staffId.'/permanent')->assertOk();
    $this->deleteJson('/settings/staff-roles/'.$role->id.'/permanent')->assertOk();
    expect(StaffRole::withTrashed()->find($role->id))->toBeNull();
});

it('saves fine-grained permissions and enforces them on module routes', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [],
    ]);

    $this->actingAs($staff)->get('/sales')->assertRedirect('/no-access');

    $permissions = [];
    foreach ([
        'dashboard' => ['todaySales', 'todayProfit', 'todayExpenses', 'quickActions'],
        'pos' => ['sell', 'printDocket'],
        'sales' => ['view', 'viewOthers', 'profit', 'edit', 'backdate', 'delete', 'reprint', 'printBill', 'export'],
        'kitchen' => ['rawAdd', 'rawEdit', 'rawDelete', 'rawValue', 'recipeAdd', 'recipeEdit', 'recipeDelete', 'foodAdd', 'foodEdit', 'foodDelete'],
        'inventory' => ['addRestock', 'editAdjust', 'transfer', 'delete', 'value'],
        'expenses' => ['add', 'delete', 'view'],
        'reports' => ['viewExport'],
        'staff' => ['add'],
        'rooms' => ['view', 'checkIn', 'checkOut', 'reservations', 'housekeeping', 'manage', 'finance'],
        'settings' => ['manage'],
    ] as $module => $components) {
        $permissions[$module] = [
            'enabled' => $module === 'sales',
            'components' => array_fill_keys($components, false),
        ];
    }
    $permissions['sales']['components']['view'] = true;
    $permissions['sales']['components']['viewOthers'] = true;

    $this->actingAs(User::query()->where('role', 'administrator')->firstOrFail());
    $this->putJson('/staff/'.$staff->id.'/permissions', ['permissions' => $permissions])
        ->assertOk();
    expect($staff->fresh()->hasPermission('sales', 'view'))->toBeTrue()
        ->and($staff->fresh()->hasPermission('sales', 'viewOthers'))->toBeTrue()
        ->and($staff->fresh()->hasPermission('sales', 'delete'))->toBeFalse();

    $this->actingAs($staff->fresh())->get('/sales')->assertOk();
    $this->actingAs($staff->fresh())->postJson('/sales/bulk-delete', ['orders' => [1]])->assertForbidden();
});

it('keeps Settings administrator-only for staff accounts', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => ['settings' => ['enabled' => true, 'components' => ['manage' => true]]],
    ]);

    $this->actingAs($staff)->get('/settings')->assertRedirect('/no-access');
});

it('redirects an attendant away from a page after its permission is revoked', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'sales' => ['enabled' => true, 'components' => ['view' => true]],
            'pos' => ['enabled' => true, 'components' => ['sell' => true]],
        ],
    ]);

    $this->actingAs($staff)->get('/sales')->assertOk();

    $staff->update([
        'permissions' => ['pos' => ['enabled' => true, 'components' => ['sell' => true]]],
    ]);

    $this->get('/sales')->assertRedirect('/pos');
    $this->get('/pos')->assertOk();
});

it('requires administrator password verification and prevents inactive staff login', function () {
    $admin = auth()->user();
    $this->postJson('/staff/authorize', ['password' => 'wrong-password'])->assertUnprocessable();
    $this->postJson('/staff/authorize', ['password' => 'password'])->assertOk();

    $staff = User::factory()->create(['role' => 'cashier', 'pin' => '9071', 'status' => 'inactive']);
    $this->post('/logout')->assertRedirect(route('login'));
    $this->post('/login', ['pin' => $staff->pin])->assertSessionHasErrors('pin');
});

it('allows attendants with staff-add access to open permission controls only after administrator password verification', function () {
    $administrator = auth()->user();
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'staff' => ['enabled' => true, 'components' => ['add' => true]],
        ],
    ]);

    $this->actingAs($staff)->get('/staff')
        ->assertOk()
        ->assertSee('canAddStaff: true', false)
        ->assertSee('isAdministrator || canAddStaff', false);

    $permissions = [];
    foreach ([
        'dashboard' => ['todaySales', 'todayProfit', 'todayExpenses', 'quickActions'],
        'pos' => ['sell', 'printDocket'],
        'sales' => ['view', 'viewOthers', 'profit', 'edit', 'backdate', 'delete', 'reprint', 'printBill', 'export'],
        'kitchen' => ['rawAdd', 'rawEdit', 'rawDelete', 'rawValue', 'recipeAdd', 'recipeEdit', 'recipeDelete', 'foodAdd', 'foodEdit', 'foodDelete'],
        'inventory' => ['addRestock', 'editAdjust', 'transfer', 'delete', 'value'],
        'expenses' => ['add', 'delete', 'view'],
        'reports' => ['viewExport'],
        'staff' => ['add'],
        'rooms' => ['view', 'checkIn', 'checkOut', 'reservations', 'housekeeping', 'manage', 'finance'],
        'settings' => ['manage'],
    ] as $module => $components) {
        $permissions[$module] = [
            'enabled' => $module === 'pos',
            'components' => array_fill_keys($components, false),
        ];
    }

    $this->putJson('/staff/'.$staff->id.'/permissions', ['permissions' => $permissions])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('admin_password');
    $this->postJson('/staff/authorize', ['password' => 'incorrect'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    $this->postJson('/staff/authorize', ['password' => 'password'])->assertOk();
    $this->putJson('/staff/'.$staff->id.'/permissions', [
        'permissions' => $permissions,
        'admin_password' => 'incorrect',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson('/staff/'.$staff->id.'/permissions', [
        'permissions' => $permissions,
        'admin_password' => 'password',
    ])->assertOk();

    expect($staff->fresh()->hasPermission('pos', 'sell'))->toBeFalse()
        ->and($administrator->isAdministrator())->toBeTrue();
});

it('requires the administrator password before editing or deleting a staff member', function () {
    $administrator = auth()->user();
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => [
            'staff' => ['enabled' => true, 'components' => ['add' => true]],
        ],
    ]);
    $role = StaffRole::query()->where('slug', 'cashier')->firstOrFail()->slug;
    $payload = [
        'name' => 'Updated Name',
        'phone' => '+255 700 000 000',
        'role' => $role,
        'status' => 'active',
    ];

    $this->actingAs($staff);

    $this->putJson('/staff/'.$staff->id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('admin_password');
    $this->putJson('/staff/'.$staff->id, $payload + ['admin_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    $this->deleteJson('/staff/'.$staff->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('admin_password');

    $this->putJson('/staff/'.$staff->id, $payload + ['admin_password' => 'password'])->assertOk();
    expect($staff->fresh()->name)->toBe('Updated Name');

    $this->deleteJson('/staff/'.$staff->id, ['admin_password' => 'password'])->assertOk();
    expect(User::query()->find($staff->id))->toBeNull()
        ->and(User::withTrashed()->find($staff->id))->not->toBeNull();

    $this->actingAs($administrator);
});

it('does not list the signed-in attendant among the staff members', function () {
    $attendant = User::factory()->create([
        'name' => 'Signed In Attendant',
        'role' => 'cashier',
        'status' => 'active',
        'permissions' => [
            'staff' => ['enabled' => true, 'components' => ['add' => true]],
        ],
    ]);
    $colleague = User::factory()->create([
        'name' => 'Colleague Attendant',
        'role' => 'cashier',
        'status' => 'active',
    ]);

    $this->actingAs($attendant)->get('/staff')
        ->assertOk()
        ->assertViewHas('staffMembers', function ($members) use ($attendant, $colleague) {
            $ids = $members->pluck('id')->all();

            return ! in_array($attendant->id, $ids, true)
                && in_array($colleague->id, $ids, true);
        })
        ->assertSee('Colleague Attendant');
});

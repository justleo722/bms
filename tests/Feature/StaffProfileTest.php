<?php

use App\Models\User;
use App\Settings\BarSettings;

it('shows the signed-in staff member and profile menu for every role', function () {
    $staff = User::factory()->create([
        'name' => 'Cashier Jane',
        'role' => 'cashier',
        'permissions' => [],
    ]);

    $this->actingAs($staff)
        ->get('/no-access')
        ->assertOk()
        ->assertSee('Cashier Jane')
        ->assertSee('Sign out')
        ->assertSee('profile.isAdministrator', false);
});

it('only lets administrators change the business profile from the profile form', function () {
    BarSettings::save(['name' => 'Original Bar']);

    $staff = User::factory()->create([
        'name' => 'Cashier Jane',
        'role' => 'cashier',
        'email' => 'jane@example.com',
    ]);

    $this->actingAs($staff)
        ->patchJson(route('profile.update'), [
            'name' => 'Jane Updated',
            'email' => 'jane@example.com',
            'phone' => '0700000000',
            'business_name' => 'Hijacked Bar',
        ])
        ->assertOk();

    expect($staff->fresh()->name)->toBe('Jane Updated')
        ->and($staff->fresh()->business_name)->toBeNull()
        ->and(BarSettings::all()['name'])->toBe('Original Bar');
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows an authenticated administrator to change password without the current password', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user)
        ->postJson(route('profile.password'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password changed successfully.');

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

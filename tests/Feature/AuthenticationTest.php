<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('masks the sign-in code as it is entered', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('•')
        ->assertDontSee('code[i-1]', false);
});

it('remembers a user between visits until they sign out', function () {
    User::factory()->create(['pin' => '1234']);

    $this->post('/login', ['pin' => '1234'])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(Auth::getRecallerName());

    $this->get('/settings')->assertOk();

    $this->post('/logout')->assertRedirect(route('login'));
    $this->get('/')->assertRedirect(route('login'));
});

<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->group(function () {
        Route::post('/_test/expired-session', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    });
});

it('sends an expired browser session back to the sign-in page instead of a 419 screen', function () {
    $this->post('/_test/expired-session')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your session expired. Please sign in again.');
});

it('returns a machine readable 419 to JSON clients with an expired session', function () {
    $this->postJson('/_test/expired-session')
        ->assertStatus(419)
        ->assertJson([
            'message' => 'Your session has expired. Please sign in again.',
            'session_expired' => true,
        ]);
});

it('adds baseline security headers to responses', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

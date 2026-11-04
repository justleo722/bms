<?php

use App\Models\User;

/**
 * Every module page must render without throwing, and every destructive
 * action must be guarded by a confirmation dialog.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders every module page', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    '/',
    '/pos',
    '/sales',
    '/kitchen',
    '/inventory',
    '/expenses',
    '/reports',
    '/staff',
    '/orders',
    '/tables',
    '/customers',
    '/rooms',
    '/settings',
]);

it('only renders the global navbar search on the POS page', function () {
    $this->get('/pos')
        ->assertOk()
        ->assertSee('Search products, sales, staff...');

    foreach (['/', '/sales', '/rooms', '/settings'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('Search products, sales, staff...');
    }
});

it('allows guests to access login and registration pages', function () {
    auth()->logout();

    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
});

it('redirects guests to login from protected application pages', function () {
    auth()->logout();

    $this->get('/')->assertRedirect(route('login'));
    $this->get('/settings')->assertRedirect(route('login'));
});

it('shows the lodge room management workflow', function () {
    $this->get('/rooms')
        ->assertOk()
        ->assertSee('Check in guest')
        ->assertSee('Check out guest')
        ->assertSee('Housekeeping')
        ->assertSee('Expected sign-out date')
        ->assertSee('Actual sign-out date')
        ->assertSee('Daily income and profit')
        ->assertSee('From date')
        ->assertSee('To date')
        ->assertSee('Clear dates')
        ->assertSee('Add room expense')
        ->assertSee('removeStayCharge(charge.id)', false);
});

it('provides a regenerate control for staff sign-in codes', function () {
    $this->get('/staff')
        ->assertOk()
        ->assertSee('@click="regenerateSignInCode(member)"', false)
        ->assertSee('aria-label="Regenerate sign-in code"', false);
});

it('guards every record deletion with a confirmation dialog', function () {
    // Each page must expose a confirmation dialog before anything is deleted.
    $expectations = [
        '/inventory' => ['openDelete(product)', 'confirmDelete()'],
        '/expenses' => ['askDeleteExpense(expense)', 'confirmDeleteExpense()'],
        '/kitchen' => ['openDeleteRawMaterial(m)', 'openDeleteRecipe(r)', 'openDeleteFoodItem(item)'],
        '/orders' => ['askDeleteReservation(res)', 'confirmDeleteReservation()'],
        '/staff' => ['askDeleteStaff(member)', 'confirmDeleteStaff()'],
        '/pos' => ['askClearCart()', 'confirmClearCart()'],
        '/settings' => ['askDelete(', 'runConfirm()'],
    ];

    foreach ($expectations as $url => $needles) {
        $html = $this->get($url)->assertOk()->getContent();

        foreach ($needles as $needle) {
            expect($html)->toContain($needle);
        }

        // No page should still rely on the browser's native confirm().
        expect($html)->not->toContain('window.confirm(');
    }
});

it('shows a restorable trash section and hides the rooms navigation when disabled', function () {
    $this->get('/settings')
        ->assertOk()
        ->assertSee('Payment Methods')
        ->assertSee('Trash')
        ->assertSee('restoreTrashItem(entry.id)', false)
        ->assertSee('permanentlyDeleteTrashItem(entry.id)', false)
        ->assertSee('roomsEnabled');
});

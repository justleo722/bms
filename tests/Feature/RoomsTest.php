<?php

use App\Models\Room;
use App\Models\RoomExpense;
use App\Models\RoomPayment;
use App\Models\RoomReservation;
use App\Models\RoomStay;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'administrator', 'status' => 'active']));
});

function createRoomRecord(array $overrides = []): Room
{
    $response = test()->postJson('/rooms', array_merge([
        'number' => '101',
        'type' => 'Standard',
        'floor' => 'Ground floor',
        'rate' => 85000,
        'capacity' => 2,
        'amenities' => 'Queen bed · Wi-Fi',
        'status' => 'available',
        'notes' => '',
    ], $overrides))->assertCreated();

    return Room::query()->findOrFail($response->json('room.id'));
}

it('adds, edits and deletes rooms in the database', function () {
    $room = createRoomRecord();

    expect($room->number)->toBe('101')
        ->and($room->status)->toBe('available');

    $this->putJson('/rooms/'.$room->id, [
        'number' => '101',
        'type' => 'Deluxe',
        'floor' => 'First floor',
        'rate' => 120000,
        'capacity' => 3,
        'amenities' => 'King bed · Air conditioning',
        'status' => 'maintenance',
        'notes' => 'Repaint the wall',
    ])->assertOk()
        ->assertJsonPath('data.rooms.0.type', 'Deluxe')
        ->assertJsonPath('data.rooms.0.rate', 120000)
        ->assertJsonPath('data.rooms.0.status', 'maintenance');

    $this->get('/rooms')
        ->assertOk()
        ->assertSee('Add room')
        ->assertSee('Room details are saved to the database.')
        ->assertSee('Edit room');

    $this->deleteJson('/rooms/'.$room->id)->assertOk();
    expect(Room::query()->find($room->id))->toBeNull();
});

it('keeps occupied rooms consistent with their active guest stay', function () {
    $room = createRoomRecord();

    $this->postJson('/rooms/'.$room->id.'/check-in', [
        'guest' => 'Neema Joseph',
        'phone' => '+255 754 210 456',
        'guests' => 2,
        'check_in_at' => now()->format('Y-m-d H:i:s'),
        'expected_checkout_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'rate' => 85000,
        'deposit' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.rooms.0.status', 'occupied')
        ->assertJsonPath('data.rooms.0.stay.guest', 'Neema Joseph');

    $this->putJson('/rooms/'.$room->id, [
        'number' => '101',
        'type' => 'Standard',
        'floor' => 'Ground floor',
        'rate' => 85000,
        'capacity' => 2,
        'amenities' => 'Queen bed · Wi-Fi',
        'status' => 'available',
        'notes' => '',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->deleteJson('/rooms/'.$room->id)->assertUnprocessable();
});

it('runs the full reservation, folio, checkout and housekeeping workflow', function () {
    $room = createRoomRecord();

    $this->postJson('/rooms/reservations', [
        'room_id' => $room->id,
        'guest' => 'Asha Mollel',
        'phone' => '+255 687 321 654',
        'check_in_date' => today()->toDateString(),
        'nights' => 2,
        'guests' => 2,
        'rate' => 85000,
        'deposit' => 50000,
    ])->assertCreated()
        ->assertJsonPath('data.rooms.0.status', 'reserved')
        ->assertJsonPath('data.reservations.0.guest', 'Asha Mollel');

    $reservation = RoomReservation::query()->firstOrFail();
    expect($reservation->status)->toBe('confirmed')
        ->and(RoomPayment::query()->where('room_reservation_id', $reservation->id)->value('amount'))->toEqual(50000);

    $this->postJson('/rooms/reservations/'.$reservation->id.'/check-in')
        ->assertOk()
        ->assertJsonPath('data.rooms.0.status', 'occupied')
        ->assertJsonPath('data.rooms.0.stay.guest', 'Asha Mollel');

    $stay = RoomStay::query()->firstOrFail();
    expect($stay->status)->toBe('in_house')
        ->and(RoomPayment::query()->where('room_reservation_id', $reservation->id)->value('room_stay_id'))->toBe($stay->id);

    $this->postJson('/rooms/stays/'.$stay->id.'/charges', [
        'description' => 'Dinner',
        'amount' => 28000,
    ])->assertCreated()
        ->assertJsonPath('data.rooms.0.stay.charges.0.description', 'Dinner');

    $chargeId = $stay->charges()->firstOrFail()->id;
    $this->postJson('/rooms/stays/'.$stay->id.'/payments', [
        'amount' => 40000,
        'method' => 'Mobile money',
    ])->assertCreated();

    $this->postJson('/rooms/stays/'.$stay->id.'/checkout', [
        'checkout_at' => now()->format('Y-m-d H:i:s'),
        'payment_method' => 'Cash',
        'amount_received' => 200000,
    ])->assertOk()
        ->assertJsonPath('data.rooms.0.status', 'cleaning');

    expect($stay->fresh()->status)->toBe('checked_out')
        ->and($stay->fresh()->checked_out_at)->not->toBeNull()
        ->and($stay->charges()->whereKey($chargeId)->exists())->toBeTrue()
        ->and(RoomPayment::query()->where('room_stay_id', $stay->id)->sum('amount'))->toEqual(113000);

    $this->postJson('/rooms/'.$room->id.'/ready')
        ->assertOk()
        ->assertJsonPath('data.rooms.0.status', 'available');
});

it('records and deletes room expenses and exposes them to the finance tab', function () {
    $room = createRoomRecord();

    $response = $this->postJson('/rooms/expenses', [
        'description' => 'Guest room soap',
        'category' => 'Supplies',
        'amount' => 18000,
        'expense_date' => today()->toDateString(),
        'room_id' => $room->id,
    ])->assertCreated()
        ->assertJsonPath('data.roomExpenses.0.description', 'Guest room soap');

    $expenseId = $response->json('expense.id');
    expect(RoomExpense::query()->find($expenseId))->not->toBeNull();

    $this->get('/rooms')
        ->assertOk()
        ->assertSee('Guest payments received')
        ->assertSee('Room expenses')
        ->assertSee('Add room expense');

    $this->deleteJson('/rooms/expenses/'.$expenseId)->assertOk();
    expect(RoomExpense::query()->find($expenseId))->toBeNull();
});

it('enforces room permissions on the page and its actions', function () {
    $staff = User::factory()->create([
        'role' => 'cashier',
        'status' => 'active',
        'permissions' => [
            'rooms' => [
                'enabled' => true,
                'components' => ['view' => true, 'manage' => false, 'checkIn' => false],
            ],
        ],
    ]);

    $this->actingAs($staff)->get('/rooms')
        ->assertOk()
        ->assertSee('Room board');

    $this->actingAs($staff)->postJson('/rooms', [
        'number' => '999',
        'type' => 'Standard',
        'rate' => 1,
        'capacity' => 1,
        'status' => 'available',
    ])->assertForbidden();

    $room = Room::query()->create([
        'number' => '102',
        'type' => 'Standard',
        'rate' => 85000,
        'capacity' => 2,
        'status' => 'available',
    ]);
    $this->actingAs($staff)->postJson('/rooms/'.$room->id.'/check-in', [
        'guest' => 'Blocked Guest',
        'phone' => '+255 700 000 000',
        'guests' => 1,
        'check_in_at' => now()->format('Y-m-d H:i:s'),
        'expected_checkout_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'rate' => 85000,
        'deposit' => 0,
    ])->assertForbidden();
});

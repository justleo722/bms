<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomCharge;
use App\Models\RoomExpense;
use App\Models\RoomPayment;
use App\Models\RoomReservation;
use App\Models\RoomStay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    private const EXPENSE_CATEGORIES = ['Supplies', 'Utilities', 'Repairs', 'Laundry', 'Other'];

    public function index(Request $request)
    {
        $user = $request->user();

        return view('pages.rooms', [
            'activeNav' => 'rooms',
            'roomsState' => $this->state(),
            'roomsToday' => now()->toDateString(),
            'roomTypes' => Room::query()->distinct()->orderBy('type')->pluck('type')
                ->merge(['Standard', 'Deluxe', 'Family', 'Executive'])
                ->unique()->values(),
            'roomPermissions' => [
                'view' => $user?->hasPermission('rooms', 'view') ?? false,
                'checkIn' => $user?->hasPermission('rooms', 'checkIn') ?? false,
                'checkOut' => $user?->hasPermission('rooms', 'checkOut') ?? false,
                'reservations' => $user?->hasPermission('rooms', 'reservations') ?? false,
                'housekeeping' => $user?->hasPermission('rooms', 'housekeeping') ?? false,
                'manage' => $user?->hasPermission('rooms', 'manage') ?? false,
                'finance' => $user?->hasPermission('rooms', 'finance') ?? false,
            ],
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->state()]);
    }

    /**
     * Lightweight board payload for live room-status refreshes.
     * Excludes financial history and completed stays.
     */
    public function board(): JsonResponse
    {
        $rooms = Room::query()->orderBy('number')->get();
        $stays = RoomStay::query()
            ->where('status', 'in_house')
            ->with([
                'charges' => fn ($query) => $query->orderBy('id'),
                'payments' => fn ($query) => $query->orderBy('id'),
            ])
            ->get()
            ->keyBy('room_id');
        $reservations = RoomReservation::query()
            ->where('status', 'confirmed')
            ->with('room')
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get();
        $reservationsByRoom = $reservations->keyBy('room_id');

        $revision = md5(implode('|', [
            $rooms->count(), $rooms->max('updated_at'),
            $stays->count(), $stays->max('updated_at'),
            $reservations->count(), $reservations->max('updated_at'),
            $stays->flatMap->charges->count(), $stays->flatMap->charges->max('updated_at'),
            $stays->flatMap->payments->count(), $stays->flatMap->payments->max('updated_at'),
        ]));

        return response()->json([
            'ok' => true,
            'revision' => $revision,
            'data' => [
                'rooms' => $rooms->map(fn (Room $room) => $this->serializeRoom(
                    $room,
                    $stays->get($room->id),
                    $reservationsByRoom->get($room->id),
                ))->values(),
                'reservations' => $reservations->map(fn (RoomReservation $reservation) => $this->serializeReservation($reservation))->values(),
            ],
        ]);
    }

    public function storeRoom(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:24', Rule::unique('rooms', 'number')],
            'type' => ['required', 'string', 'max:60'],
            'floor' => ['nullable', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'amenities' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['available', 'cleaning', 'maintenance'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $room = Room::query()->create([
            'number' => trim($data['number']),
            'type' => trim($data['type']),
            'floor' => filled($data['floor'] ?? null) ? trim($data['floor']) : null,
            'rate' => $data['rate'],
            'capacity' => $data['capacity'],
            'amenities' => filled($data['amenities'] ?? null) ? trim($data['amenities']) : null,
            'status' => $data['status'],
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Room '.$room->number.' added successfully.',
            'room' => $this->serializeRoom($room->fresh(), null, null),
            'data' => $this->state(),
        ], 201);
    }

    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:24', Rule::unique('rooms', 'number')->ignore($room->id)],
            'type' => ['required', 'string', 'max:60'],
            'floor' => ['nullable', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'amenities' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(Room::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasStay = $room->currentStay()->exists();
        $hasReservation = $room->reservations()->where('status', 'confirmed')->exists();

        if ($hasStay && $data['status'] !== 'occupied') {
            throw ValidationException::withMessages([
                'status' => 'Check the current guest out before changing this room status.',
            ]);
        }
        if (! $hasStay && $data['status'] === 'occupied') {
            throw ValidationException::withMessages([
                'status' => 'Use Check in guest to move a room to occupied.',
            ]);
        }
        if ($hasReservation && $data['status'] !== 'reserved') {
            throw ValidationException::withMessages([
                'status' => 'Cancel or check in the current reservation before changing this room status.',
            ]);
        }
        if (! $hasReservation && $data['status'] === 'reserved') {
            throw ValidationException::withMessages([
                'status' => 'Use New reservation to reserve a room for a guest.',
            ]);
        }

        $room->update([
            'number' => trim($data['number']),
            'type' => trim($data['type']),
            'floor' => filled($data['floor'] ?? null) ? trim($data['floor']) : null,
            'rate' => $data['rate'],
            'capacity' => $data['capacity'],
            'amenities' => filled($data['amenities'] ?? null) ? trim($data['amenities']) : null,
            'status' => $data['status'],
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Room '.$room->number.' updated successfully.',
            'data' => $this->state(),
        ]);
    }

    public function destroyRoom(Room $room): JsonResponse
    {
        $hasHistory = $room->stays()->exists()
            || $room->reservations()->exists()
            || $room->payments()->exists()
            || $room->expenses()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'room' => 'This room has stay, reservation or finance history and cannot be deleted. Mark it as maintenance instead.',
            ]);
        }

        $room->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Room deleted successfully.',
            'data' => $this->state(),
        ]);
    }

    public function storeReservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'guest' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:60'],
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'nights' => ['required', 'integer', 'min:1', 'max:365'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $room = Room::query()->whereKey($data['room_id'])->lockForUpdate()->firstOrFail();

            if ($room->status !== 'available') {
                throw ValidationException::withMessages([
                    'room_id' => 'This room is not available for a new reservation.',
                ]);
            }
            if ($room->currentStay()->exists() || $room->reservations()->where('status', 'confirmed')->exists()) {
                throw ValidationException::withMessages([
                    'room_id' => 'This room already has an active stay or reservation.',
                ]);
            }
            if ((int) $data['guests'] > (int) $room->capacity) {
                throw ValidationException::withMessages([
                    'guests' => 'This room only sleeps '.$room->capacity.' guest(s).',
                ]);
            }

            $reservation = RoomReservation::query()->create([
                'room_id' => $room->id,
                'user_id' => $request->user()?->id,
                'guest_name' => trim($data['guest']),
                'guest_phone' => trim($data['phone']),
                'check_in_date' => $data['check_in_date'],
                'nights' => $data['nights'],
                'guests' => $data['guests'],
                'rate' => $data['rate'],
                'deposit' => $data['deposit'] ?? 0,
                'status' => 'confirmed',
            ]);

            if ((float) ($data['deposit'] ?? 0) > 0) {
                RoomPayment::query()->create([
                    'room_id' => $room->id,
                    'room_reservation_id' => $reservation->id,
                    'user_id' => $request->user()?->id,
                    'guest_name' => $reservation->guest_name,
                    'amount' => $data['deposit'],
                    'payment_method' => 'Cash',
                    'paid_on' => now()->toDateString(),
                    'note' => 'Reservation deposit',
                ]);
            }

            $room->update(['status' => 'reserved']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Reservation saved successfully.',
            'data' => $this->state(),
        ], 201);
    }

    public function cancelReservation(RoomReservation $reservation): JsonResponse
    {
        DB::transaction(function () use ($reservation) {
            $reservation = RoomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'reservation' => 'Only confirmed reservations can be cancelled.',
                ]);
            }

            $reservation->update(['status' => 'cancelled']);

            $room = Room::query()->whereKey($reservation->room_id)->lockForUpdate()->firstOrFail();
            if ($room->status === 'reserved' && ! $room->reservations()->where('status', 'confirmed')->exists()) {
                $room->update(['status' => 'available']);
            }
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Reservation cancelled.',
            'data' => $this->state(),
        ]);
    }

    public function checkInReservation(Request $request, RoomReservation $reservation): JsonResponse
    {
        DB::transaction(function () use ($request, $reservation) {
            $reservation = RoomReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is no longer waiting for check-in.',
                ]);
            }

            $room = Room::query()->whereKey($reservation->room_id)->lockForUpdate()->firstOrFail();
            if ($room->currentStay()->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'This room already has a guest checked in.',
                ]);
            }

            $checkInAt = now();
            $expectedCheckoutAt = $checkInAt->copy()->addDays((int) $reservation->nights)->setTime(11, 0);
            if ($expectedCheckoutAt->lessThanOrEqualTo($checkInAt)) {
                $expectedCheckoutAt->addDay();
            }

            $stay = RoomStay::query()->create([
                'room_id' => $room->id,
                'room_reservation_id' => $reservation->id,
                'user_id' => $request->user()?->id,
                'guest_name' => $reservation->guest_name,
                'guest_phone' => $reservation->guest_phone,
                'guests' => $reservation->guests,
                'rate' => $reservation->rate,
                'check_in_at' => $checkInAt,
                'expected_checkout_at' => $expectedCheckoutAt,
                'status' => 'in_house',
            ]);

            RoomPayment::query()
                ->where('room_reservation_id', $reservation->id)
                ->whereNull('room_stay_id')
                ->update(['room_stay_id' => $stay->id]);

            $reservation->update(['status' => 'checked_in']);
            $room->update(['status' => 'occupied']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Guest checked in successfully.',
            'data' => $this->state(),
        ]);
    }

    public function checkIn(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'guest' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:60'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
            'check_in_at' => ['required', 'date'],
            'expected_checkout_at' => ['required', 'date', 'after:check_in_at'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        DB::transaction(function () use ($data, $request, $room) {
            $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->status !== 'available') {
                throw ValidationException::withMessages([
                    'room' => 'This room is not available for check-in.',
                ]);
            }
            if ($room->currentStay()->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'This room already has a guest checked in.',
                ]);
            }
            if ($room->reservations()->where('status', 'confirmed')->exists()) {
                throw ValidationException::withMessages([
                    'room' => 'This room has a reservation. Check the guest in from the Reservations tab.',
                ]);
            }
            if ((int) $data['guests'] > (int) $room->capacity) {
                throw ValidationException::withMessages([
                    'guests' => 'This room only sleeps '.$room->capacity.' guest(s).',
                ]);
            }

            $stay = RoomStay::query()->create([
                'room_id' => $room->id,
                'user_id' => $request->user()?->id,
                'guest_name' => trim($data['guest']),
                'guest_phone' => trim($data['phone']),
                'guests' => $data['guests'],
                'rate' => $data['rate'],
                'check_in_at' => $data['check_in_at'],
                'expected_checkout_at' => $data['expected_checkout_at'],
                'status' => 'in_house',
            ]);

            if ((float) ($data['deposit'] ?? 0) > 0) {
                RoomPayment::query()->create([
                    'room_id' => $room->id,
                    'room_stay_id' => $stay->id,
                    'user_id' => $request->user()?->id,
                    'guest_name' => $stay->guest_name,
                    'amount' => $data['deposit'],
                    'payment_method' => 'Cash',
                    'paid_on' => now()->toDateString(),
                    'note' => 'Room deposit',
                ]);
            }

            $room->update(['status' => 'occupied']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Guest checked in successfully.',
            'data' => $this->state(),
        ]);
    }

    public function storeCharge(Request $request, RoomStay $stay): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ]);

        DB::transaction(function () use ($data, $request, $stay) {
            $stay = RoomStay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            if ($stay->status !== 'in_house') {
                throw ValidationException::withMessages([
                    'stay' => 'Charges can only be added to an in-house guest.',
                ]);
            }

            RoomCharge::query()->create([
                'room_stay_id' => $stay->id,
                'user_id' => $request->user()?->id,
                'description' => trim($data['description']),
                'amount' => $data['amount'],
            ]);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Charge added to the guest folio.',
            'data' => $this->state(),
        ], 201);
    }

    public function destroyCharge(RoomStay $stay, RoomCharge $charge): JsonResponse
    {
        if ((int) $charge->room_stay_id !== (int) $stay->id) {
            abort(404);
        }
        if ($stay->status !== 'in_house') {
            throw ValidationException::withMessages([
                'stay' => 'Charges can only be removed from an in-house guest.',
            ]);
        }

        $charge->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Charge removed from the guest folio.',
            'data' => $this->state(),
        ]);
    }

    public function storePayment(Request $request, RoomStay $stay): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'method' => ['required', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($data, $request, $stay) {
            $stay = RoomStay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            if ($stay->status !== 'in_house') {
                throw ValidationException::withMessages([
                    'stay' => 'Payments can only be recorded for an in-house guest.',
                ]);
            }

            RoomPayment::query()->create([
                'room_id' => $stay->room_id,
                'room_stay_id' => $stay->id,
                'user_id' => $request->user()?->id,
                'guest_name' => $stay->guest_name,
                'amount' => $data['amount'],
                'payment_method' => $data['method'],
                'paid_on' => now()->toDateString(),
                'note' => 'Guest payment',
            ]);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Guest payment recorded.',
            'data' => $this->state(),
        ], 201);
    }

    public function checkout(Request $request, RoomStay $stay): JsonResponse
    {
        $data = $request->validate([
            'checkout_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:120'],
            'amount_received' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        DB::transaction(function () use ($data, $request, $stay) {
            $stay = RoomStay::query()->with(['charges', 'payments', 'room', 'reservation'])
                ->whereKey($stay->id)->lockForUpdate()->firstOrFail();

            if ($stay->status !== 'in_house') {
                throw ValidationException::withMessages([
                    'stay' => 'This guest has already checked out.',
                ]);
            }

            $checkoutAt = Carbon::parse($data['checkout_at']);
            if ($checkoutAt->lessThan($stay->check_in_at)) {
                throw ValidationException::withMessages([
                    'checkout_at' => 'Checkout cannot be before the check-in time.',
                ]);
            }

            $days = $this->daysStayed($stay->check_in_at, $checkoutAt);
            $total = round(((float) $stay->rate * $days) + (float) $stay->charges->sum('amount'), 2);
            $paid = round((float) $stay->payments->sum('amount'), 2);
            $balanceDue = max(0, round($total - $paid, 2));

            if ((float) $data['amount_received'] + 0.001 < $balanceDue) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Amount received is less than the balance due of '.number_format($balanceDue, 2).'.',
                ]);
            }

            if ($balanceDue > 0) {
                RoomPayment::query()->create([
                    'room_id' => $stay->room_id,
                    'room_stay_id' => $stay->id,
                    'user_id' => $request->user()?->id,
                    'guest_name' => $stay->guest_name,
                    'amount' => $balanceDue,
                    'payment_method' => $data['payment_method'],
                    'paid_on' => $checkoutAt->toDateString(),
                    'note' => 'Checkout payment',
                ]);
            }

            $stay->update([
                'status' => 'checked_out',
                'checked_out_at' => $checkoutAt,
            ]);
            $stay->room->update(['status' => 'cleaning']);

            if ($stay->reservation) {
                $stay->reservation->update(['status' => 'completed']);
            }
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Guest checked out. The room is now waiting for housekeeping.',
            'data' => $this->state(),
        ]);
    }

    public function markReady(Room $room): JsonResponse
    {
        DB::transaction(function () use ($room) {
            $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($room->status !== 'cleaning') {
                throw ValidationException::withMessages([
                    'room' => 'Only rooms that need cleaning can be marked ready.',
                ]);
            }

            $room->update(['status' => 'available']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Room is ready for the next guest.',
            'data' => $this->state(),
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(self::EXPENSE_CATEGORIES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
        ]);

        $expense = RoomExpense::query()->create([
            'room_id' => $data['room_id'] ?? null,
            'user_id' => $request->user()?->id,
            'description' => trim($data['description']),
            'category' => $data['category'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Room expense saved.',
            'expense' => $this->serializeExpense($expense->fresh('room')),
            'data' => $this->state(),
        ], 201);
    }

    public function destroyExpense(RoomExpense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Room expense deleted.',
            'data' => $this->state(),
        ]);
    }

    /**
     * Every database-backed collection used by the Rooms page.
     *
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $rooms = Room::query()->orderBy('number')->get();
        $stays = RoomStay::query()
            ->where('status', 'in_house')
            ->with([
                'charges' => fn ($query) => $query->orderBy('id'),
                'payments' => fn ($query) => $query->orderBy('id'),
            ])
            ->get()
            ->keyBy('room_id');
        $reservations = RoomReservation::query()
            ->where('status', 'confirmed')
            ->with('room')
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get();
        $reservationsByRoom = $reservations->keyBy('room_id');
        $payments = RoomPayment::query()->with('room')->orderByDesc('paid_on')->orderByDesc('id')->get();
        $expenses = RoomExpense::query()->with('room')->orderByDesc('expense_date')->orderByDesc('id')->get();
        $completedStays = RoomStay::query()
            ->where('status', 'checked_out')
            ->with(['room', 'charges', 'payments'])
            ->orderByDesc('checked_out_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return [
            'rooms' => $rooms->map(fn (Room $room) => $this->serializeRoom(
                $room,
                $stays->get($room->id),
                $reservationsByRoom->get($room->id),
            ))->values(),
            'reservations' => $reservations->map(fn (RoomReservation $reservation) => $this->serializeReservation($reservation))->values(),
            'incomePayments' => $payments->map(fn (RoomPayment $payment) => $this->serializePayment($payment))->values(),
            'roomExpenses' => $expenses->map(fn (RoomExpense $expense) => $this->serializeExpense($expense))->values(),
            'completedStays' => $completedStays->map(fn (RoomStay $stay) => $this->serializeCompletedStay($stay))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRoom(Room $room, ?RoomStay $stay = null, ?RoomReservation $reservation = null): array
    {
        return [
            'id' => $room->id,
            'number' => $room->number,
            'type' => $room->type,
            'floor' => $room->floor ?? '',
            'rate' => (float) $room->rate,
            'status' => $room->status,
            'capacity' => (int) $room->capacity,
            'amenities' => $room->amenities ?? '',
            'notes' => $room->notes ?? '',
            'stay' => $stay ? $this->serializeStay($stay) : null,
            'reservation' => $reservation ? $this->serializeReservation($reservation) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStay(RoomStay $stay): array
    {
        return [
            'id' => $stay->id,
            'guest' => $stay->guest_name,
            'phone' => $stay->guest_phone,
            'guests' => (int) $stay->guests,
            'checkInAt' => $stay->check_in_at->format('Y-m-d\TH:i'),
            'expectedCheckoutAt' => $stay->expected_checkout_at->format('Y-m-d\TH:i'),
            'rate' => (float) $stay->rate,
            'reservationId' => $stay->room_reservation_id,
            'charges' => $stay->charges->map(fn (RoomCharge $charge) => [
                'id' => $charge->id,
                'description' => $charge->description,
                'amount' => (float) $charge->amount,
            ])->values(),
            'payments' => $stay->payments->map(fn (RoomPayment $payment) => $this->serializeStayPayment($payment))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReservation(RoomReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'roomId' => $reservation->room_id,
            'room' => $reservation->relationLoaded('room') ? $reservation->room?->number : null,
            'guest' => $reservation->guest_name,
            'phone' => $reservation->guest_phone,
            'checkInDate' => $reservation->check_in_date->toDateString(),
            'nights' => (int) $reservation->nights,
            'guests' => (int) $reservation->guests,
            'rate' => (float) $reservation->rate,
            'deposit' => (float) $reservation->deposit,
            'status' => $reservation->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStayPayment(RoomPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
            'date' => $payment->paid_on->toDateString(),
            'note' => $payment->note ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(RoomPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'roomId' => $payment->room_id,
            'room' => $payment->room?->number ?? '',
            'guest' => $payment->guest_name,
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
            'date' => $payment->paid_on->toDateString(),
            'note' => $payment->note ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExpense(RoomExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'roomId' => $expense->room_id,
            'room' => $expense->room?->number ?? '',
            'description' => $expense->description,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'date' => $expense->expense_date->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCompletedStay(RoomStay $stay): array
    {
        $days = $stay->checked_out_at
            ? $this->daysStayed($stay->check_in_at, $stay->checked_out_at)
            : 1;
        $total = round(((float) $stay->rate * $days) + (float) $stay->charges->sum('amount'), 2);
        $paid = round((float) $stay->payments->sum('amount'), 2);

        return [
            'id' => $stay->id,
            'guest' => $stay->guest_name,
            'room' => $stay->room?->number ?? '',
            'checkInAt' => $stay->check_in_at->format('Y-m-d\TH:i'),
            'checkOutAt' => $stay->checked_out_at?->format('Y-m-d\TH:i'),
            'days' => $days,
            'total' => $total,
            'paid' => $paid,
            'balance' => max(0, round($total - $paid, 2)),
        ];
    }

    private function daysStayed(CarbonInterface $start, CarbonInterface $end): int
    {
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($seconds <= 0) {
            return 1;
        }

        return max(1, (int) ceil($seconds / 86400));
    }
}

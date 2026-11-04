<?php

namespace App\Http\Controllers;

use App\Models\InventoryCounter;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    private const PERMISSION_MODULES = [
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
    ];

    public function index(Request $request)
    {
        return view('pages.staff', [
            'activeNav' => 'staff',
            'staffMembers' => $this->staffMembers($request),
            'staffRoles' => $this->staffRoles(),
            'staffCounters' => InventoryCounter::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Lightweight payload used by the Staff page to refresh itself without a reload.
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'staff' => $this->staffMembers($request),
            'roles' => $this->staffRoles(),
            'counters' => InventoryCounter::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function staffMembers(Request $request)
    {
        $currentUserId = $request->user()?->id;

        return User::query()->with('counter')->where('role', '!=', 'administrator')
            ->when($currentUserId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->orderBy('name')->get()->map(fn (User $user) => $this->serializeStaff($user));
    }

    private function staffRoles()
    {
        return StaffRole::query()->orderBy('name')->get()->map(fn (StaffRole $role) => $this->serializeRole($role));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', Rule::exists('staff_roles', 'slug')->whereNull('deleted_at')],
            'counter_id' => ['nullable', 'integer', 'exists:inventory_counters,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user = DB::transaction(function () use ($data) {
            $pin = $this->generateUniquePin();
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => 'staff-'.Str::uuid().'@staff.local',
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'pin' => $pin,
                'password' => Str::random(48),
                'counter_id' => $data['counter_id'] ?? null,
                'status' => $data['status'],
                'permissions' => [],
            ]);

            $user->setAttribute('pin', $pin);

            return $user->load('counter');
        });

        return response()->json([
            'ok' => true,
            'message' => 'Staff member added successfully.',
            'staff' => $this->serializeStaff($user, revealPin: true),
        ], 201);
    }

    public function update(Request $request, User $staff): JsonResponse
    {
        $this->ensureStaffAccount($staff);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', Rule::exists('staff_roles', 'slug')->whereNull('deleted_at')],
            'counter_id' => ['nullable', 'integer', 'exists:inventory_counters,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'admin_password' => [Rule::requiredIf(! $request->user()?->isAdministrator()), 'nullable', 'string'],
        ]);
        $this->verifyAdministratorPassword($data['admin_password'] ?? null);

        $staff->update([
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'counter_id' => $data['counter_id'] ?? null,
            'status' => $data['status'],
        ]);

        return response()->json(['ok' => true, 'staff' => $this->serializeStaff($staff->fresh('counter'))]);
    }

    public function destroy(Request $request, User $staff): JsonResponse
    {
        $this->ensureStaffAccount($staff);
        $data = $request->validate([
            'admin_password' => [Rule::requiredIf(! $request->user()?->isAdministrator()), 'nullable', 'string'],
        ]);
        $this->verifyAdministratorPassword($data['admin_password'] ?? null);

        $staff->delete();

        return response()->json(['ok' => true, 'message' => 'Staff member moved to Settings trash.']);
    }

    public function updatePermissions(Request $request, User $staff): JsonResponse
    {
        $this->ensureStaffAccount($staff);
        $rules = [
            'permissions' => ['required', 'array:'.implode(',', array_keys(self::PERMISSION_MODULES))],
            'admin_password' => [Rule::requiredIf(! $request->user()?->isAdministrator()), 'nullable', 'string'],
        ];
        foreach (self::PERMISSION_MODULES as $module => $components) {
            $rules["permissions.{$module}"] = ['required', 'array:enabled,components'];
            $rules["permissions.{$module}.enabled"] = ['required', 'boolean'];
            $rules["permissions.{$module}.components"] = ['required', 'array:'.implode(',', $components)];
            foreach ($components as $component) {
                $rules["permissions.{$module}.components.{$component}"] = ['required', 'boolean'];
            }
        }
        $data = $request->validate($rules);
        $this->verifyAdministratorPassword($data['admin_password'] ?? null);

        $staff->update(['permissions' => $data['permissions']]);

        return response()->json(['ok' => true, 'permissions' => $staff->fresh()->permissions]);
    }

    public function regeneratePin(User $staff): JsonResponse
    {
        $this->ensureStaffAccount($staff);
        $pin = $this->generateUniquePin();
        $staff->update(['pin' => $pin]);

        return response()->json(['ok' => true, 'pin' => $pin]);
    }

    public function revealPin(User $staff): JsonResponse
    {
        $this->ensureStaffAccount($staff);

        return response()->json(['ok' => true, 'pin' => $staff->pin]);
    }

    public function authorizeAdministrator(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! $this->isAdministratorPassword($data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'The administrator password is incorrect.',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function verifyAdministratorPassword(?string $password): void
    {
        if ($password === null || $password === '') {
            return;
        }

        if (! $this->isAdministratorPassword($password)) {
            throw ValidationException::withMessages([
                'password' => 'The administrator password is incorrect.',
            ]);
        }
    }

    private function isAdministratorPassword(string $password): bool
    {
        return User::query()->where('role', 'administrator')->where('status', 'active')
            ->select('id', 'password')->get()
            ->contains(fn (User $administrator) => Hash::check($password, $administrator->password));
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('staff_roles', 'name')->whereNull('deleted_at'),
            ],
        ]);
        $slug = Str::slug($data['name']);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'Enter a role name containing letters or numbers.']);
        }
        if ($slug === 'administrator') {
            throw ValidationException::withMessages(['name' => 'The Administrator role is reserved.']);
        }
        if (StaffRole::withTrashed()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'That role key has already been used.']);
        }

        $role = StaffRole::query()->create(['name' => trim($data['name']), 'slug' => $slug]);

        return response()->json(['ok' => true, 'role' => $this->serializeRole($role)], 201);
    }

    public function destroyRole(StaffRole $role): JsonResponse
    {
        $role->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreRole(int $role): JsonResponse
    {
        $record = StaffRole::withTrashed()->findOrFail($role);
        $record->restore();

        return response()->json(['ok' => true, 'role' => $this->serializeRole($record)]);
    }

    public function permanentlyDeleteRole(int $role): JsonResponse
    {
        $record = StaffRole::withTrashed()->findOrFail($role);
        if (User::withTrashed()->where('role', $record->slug)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This role is assigned to saved staff accounts and cannot be permanently deleted.',
            ], 422);
        }
        $record->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function restoreStaff(int $staff): JsonResponse
    {
        $record = User::withTrashed()->findOrFail($staff);
        $this->ensureStaffAccount($record);
        $record->restore();

        return response()->json(['ok' => true]);
    }

    public function permanentlyDeleteStaff(int $staff): JsonResponse
    {
        $record = User::withTrashed()->findOrFail($staff);
        $this->ensureStaffAccount($record);
        $record->forceDelete();

        return response()->json(['ok' => true]);
    }

    private function serializeStaff(User $user, bool $revealPin = false): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->status ?? 'active',
            'phone' => $user->phone ?? '',
            'counterId' => $user->counter_id,
            'counter' => $user->counter?->name ?? 'All Counters',
            'initials' => $user->initials(),
            'signInCode' => $revealPin ? $user->getAttribute('pin') : null,
            'permissions' => $user->permissions ?? [],
            'color' => 'bg-gradient-to-br from-blue-500 to-cyan-500',
        ];
    }

    private function serializeRole(StaffRole $role): array
    {
        return ['id' => $role->slug, 'recordId' => $role->id, 'label' => $role->name];
    }

    private function ensureStaffAccount(User $user): void
    {
        abort_if($user->isAdministrator(), 422, 'The administrator account cannot be managed as staff.');
    }

    private function generateUniquePin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::withTrashed()->where('pin', $pin)->exists());

        return $pin;
    }
}

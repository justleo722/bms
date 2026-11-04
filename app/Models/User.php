<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'business_name',
        'email',
        'phone',
        'business_address',
        'role',
        'pin',
        'password',
        'status',
        'counter_id',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /**
     * Whether this account manages the installation.
     */
    public function isAdministrator(): bool
    {
        return ($this->role ?? 'administrator') === 'administrator';
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(InventoryCounter::class, 'counter_id');
    }

    public function hasPermission(string $module, ?string $component = null): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        $modulePermission = $permissions[$module] ?? null;

        if (! is_array($modulePermission) || empty($modulePermission['enabled'])) {
            return false;
        }

        $components = $modulePermission['components'] ?? [];
        if ($component !== null) {
            return ! empty($components[$component]);
        }

        // An enabled module with no component list is module-level access.
        // When components are defined, at least one must be granted.
        return ! is_array($components) || count($components) === 0 || count(array_filter($components)) > 0;
    }

    public function landingPath(): string
    {
        if ($this->isAdministrator() || $this->hasPermission('dashboard')) {
            return '/';
        }

        foreach ([
            'pos' => '/pos',
            'sales' => '/sales',
            'kitchen' => '/kitchen',
            'inventory' => '/inventory',
            'expenses' => '/expenses',
            'reports' => '/reports',
            'staff' => '/staff',
            'rooms' => '/rooms',
        ] as $module => $path) {
            if ($this->hasPermission($module)) {
                return $path;
            }
        }

        return '/no-access';
    }

    /**
     * The initials shown in the avatar badge.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (! $parts) {
            return 'A';
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
    }
}

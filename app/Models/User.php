<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = ['email', 'full_name', 'phone', 'password_hash'];
    protected $hidden = ['password_hash', 'remember_token'];
    protected $attributes = ['is_active' => true, 'is_email_verified' => false, 'is_phone_verified' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_email_verified' => 'boolean', 'is_phone_verified' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['business_id', 'branch_id', 'expires_at', 'granted_by']);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function hasRole(string $code, ?int $businessId = null, ?int $branchId = null): bool
    {
        if (! $this->is_active || $this->trashed()) {
            return false;
        }

        return $this->roles()->where('code', $code)
            ->wherePivot('business_id', $businessId)
            ->wherePivot('branch_id', $branchId)
            ->where(fn ($query) => $query->whereNull('user_roles.expires_at')
                ->orWhere('user_roles.expires_at', '>', now()))
            ->exists();
    }
}

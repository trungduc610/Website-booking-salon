<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use SoftDeletes;

    protected $fillable = ['full_name', 'position', 'bio', 'employee_code', 'is_bookable', 'status'];
    protected function casts(): array
    {
        return ['is_bookable' => 'boolean'];
    }
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services', 'staff_id', 'service_id');
    }
    public function hours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class, 'staff_id');
    }
    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class, 'staff_id');
    }
}

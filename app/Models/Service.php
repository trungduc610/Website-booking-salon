<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'name', 'description', 'price', 'duration_minutes', 'bookable', 'status'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'bookable' => 'boolean', 'duration_minutes' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}

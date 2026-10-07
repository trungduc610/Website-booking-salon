<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['code', 'name', 'level'];
}

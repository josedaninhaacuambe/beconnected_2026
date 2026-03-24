<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['name', 'code'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}

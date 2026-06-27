<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = [
        'name',
        'municipality',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function nurses(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<ChildProfile, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChildProfile::class);
    }
}

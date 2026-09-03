<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['name', 'code'];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
}

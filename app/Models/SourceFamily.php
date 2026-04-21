<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceFamily extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'editorial_ownership',
        'description',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }
}

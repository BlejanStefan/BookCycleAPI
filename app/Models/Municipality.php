<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Municipality extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'province_id'];

    // Relación hacia su provincia
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    // Relación con los usuarios que viven allí
    public function users() : HasMany
    {
        return $this->hasMany(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Province extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'community_id'];


    // Relación hacia arriba (Padre)
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

// Relación hacia abajo (Hijos)
    public function municipalities() : HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}

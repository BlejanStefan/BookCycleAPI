<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'isbn13',
        'ol_edition_key',
        'title',
        'year',
    ];

    // Relación por si la necesitas luego (según tu ER)
    public function listings()
    {
        return $this->hasMany(Listing::class);
    }
}

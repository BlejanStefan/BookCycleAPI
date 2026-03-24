<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    protected $fillable = [
        'isbn13',
        'ol_edition_key',
        'title',
        'year',
    ];
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'author_book');
    }

    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Publisher::class, 'book_publisher');
    }

    // Relación por si la necesitas luego (según tu ER)
    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Listing extends Model
{
    protected $fillable = [
        'book_id', 'user_id', 'category_id', 'municipality_id',
        'price', 'condition', 'status', 'description'
    ];

    public function images() :HasMany {
        return $this->hasMany(ListingImage::class)->orderBy('order');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }


}

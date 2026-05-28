<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ListingImage extends Model
{
    protected $fillable = ['listing_id', 'path', 'order'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}

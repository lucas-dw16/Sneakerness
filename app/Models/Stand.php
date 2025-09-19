<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stand extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'vendor_id',
        'name',
        'location',
        'size_sqm',
        'price_eur',
        'is_reserved',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}

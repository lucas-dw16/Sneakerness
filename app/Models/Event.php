<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'starts_at',
        'ends_at',
        'location',
        'capacity',
        'description',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
        static::updating(function (self $event) {
            if ($event->isDirty('name')) {
                $event->slug = Str::slug($event->name);
            }
        });
    }
}

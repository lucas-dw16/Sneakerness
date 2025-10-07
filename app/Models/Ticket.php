<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class Ticket extends Model
{
    use HasFactory;

    // Ticket = aankoop van event tickets (aggregatie van aantal) – niet support.
    protected $fillable = [
        'user_id',
        'event_id',
        'type',        // regular | vip
        'quantity',
        'unit_price',
        'total_price',
        'status',      // pending | paid | cancelled
    ];

    protected $casts = [
        'unit_price' => 'float',
        'total_price' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::saving(function (Ticket $model) {
            // Automatisch totaal berekenen (zorg voor juiste decimal precisie).
            if ($model->quantity && $model->unit_price !== null) {
                /** @var float $computed */
                $computed = round(((float)$model->quantity * (float)$model->unit_price), 2);
                // Toegewezen als float; Eloquent cast 'decimal:2' verzorgt consistente representatie.
                $model->total_price = $computed;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeVisibleFor(Builder $query, $viewer = null): Builder
    {
        $viewer = $viewer ?: Auth::user();
        if (! $viewer) return $query->whereRaw('1=0');
        if ($viewer->hasAnyRole(['admin','support'])) return $query; // alles
        return $query->where('user_id', $viewer->id);
    }
}

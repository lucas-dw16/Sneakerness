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

    protected $fillable = [
        'user_id',
        'vendor_id',
        'subject',
        'description',
        'status',
        'priority',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeVisibleFor(Builder $query, $viewer = null): Builder
    {
        $viewer = $viewer ?: Auth::user();
        if (! $viewer) return $query->whereRaw('1=0');
        if ($viewer->hasAnyRole(['admin','support'])) return $query; // all
        if ($viewer->hasAnyRole(['verkoper','contactpersoon'])) {
            return $query->where('vendor_id', $viewer->vendor_id);
        }
        // generic user: only own tickets
        return $query->where('user_id', $viewer->id);
    }
}

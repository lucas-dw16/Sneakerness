<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'name', 'email', 'phone', 'role_label', 'is_primary'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ContactPerson $contact) {
            // If this contact is marked primary, unset others for the same vendor.
            if ($contact->is_primary && $contact->vendor_id) {
                static::where('vendor_id', $contact->vendor_id)
                    ->where('id', '!=', $contact->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }
}

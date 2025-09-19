<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'status',
        'vat_number',
        'kvk_number',
        'billing_email',
        'website',
        'billing_address',
        'notes',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

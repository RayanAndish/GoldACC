<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute; // Keep existing import
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Add BelongsTo for clarity
use Morilog\Jalali\Jalalian; // <-- Add this line for Jalali

class License extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'system_id',
        'license_key_hash',
        'license_key_display',
        'salt',
        'hardware_id_hash',
        'license_type',
        'features', // JSON column
        'request_code_hash',
        'ip_hash',
        'status',
        'expires_at',
        'activated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'features' => 'array', // Automatically handle JSON encode/decode
        // No need to cast created_at/updated_at if they are standard timestamps
    ];

    /**
     * Get the system that owns the license.
     */
    public function system(): BelongsTo // Specify return type
    {
        return $this->belongsTo(System::class);
    }

    // --- Jalali Date Accessors ---

    /**
     * Get the creation date in Jalali format.
     *
     * @return string|null
     */
    protected function getJalaliCreatedAtAttribute(): ?string
    {
        return $this->created_at ? Jalalian::fromCarbon($this->created_at)->format('Y/m/d H:i') : null;
    }

    /**
     * Get the last update date in Jalali format.
     *
     * @return string|null
     */
    protected function getJalaliUpdatedAtAttribute(): ?string
    {
        return $this->updated_at ? Jalalian::fromCarbon($this->updated_at)->format('Y/m/d H:i') : null;
    }

    /**
     * Get the expiry date in Jalali format (Date only).
     *
     * @return string|null
     */
    protected function getJalaliExpiresAtAttribute(): ?string
    {
        // Use Y/m/d format for date-only fields like expiry date
        return $this->expires_at ? Jalalian::fromCarbon($this->expires_at)->format('Y/m/d') : null;
    }

     /**
      * Get the activation date in Jalali format.
      *
      * @return string|null
      */
    protected function getJalaliActivatedAtAttribute(): ?string
    {
         return $this->activated_at ? Jalalian::fromCarbon($this->activated_at)->format('Y/m/d H:i') : null;
    }

}
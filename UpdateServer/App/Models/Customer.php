<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Import HasFactory
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import BelongsTo
use Illuminate\Database\Eloquent\Relations\HasMany; // Import HasMany (already used implicitly)

class Customer extends Model
{
    use HasFactory; // Add HasFactory trait

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'user_id' // Add user_id to fillable properties
    ];

    /**
     * Get the user that owns the customer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the systems associated with the customer.
     */
    public function systems(): HasMany
    {
        return $this->hasMany(System::class);
    }

    /**
     * Get the activity logs for the customer.
     */
    public function customerActivityLogs(): HasMany
    {
        return $this->hasMany(CustomerActivityLog::class);
    }

    /**
     * Get the SMS logs for the customer.
     */
    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    /**
     * Get the email logs for the customer.
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
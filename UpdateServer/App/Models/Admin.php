<?php // app/Models/Admin.php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Use Authenticatable
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable // Extend Authenticatable
{
    use HasFactory, Notifiable;

    // Important: Specify the guard this model uses if it's not the default
    protected $guard = 'admin';

    /**
     * The attributes that are mass assignable.
     * Adjust based on your admins table columns
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            // 'email_verified_at' => 'datetime', // Removed as per decision
            'password' => 'hashed',
        ];
    }
}
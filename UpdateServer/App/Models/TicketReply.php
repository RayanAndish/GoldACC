<?php // app/Models/TicketReply.php

namespace App\Models;
use Morilog\Jalali\Jalalian;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'admin_id',
        'message',
    ];

    /**
     * Get the ticket that the reply belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
       /**
     * Get the creation date in Jalali format.
     * @return string|null
     */
    protected function getJalaliCreatedAtAttribute(): ?string
    {
        return $this->created_at ? Jalalian::fromCarbon($this->created_at)->format('Y/m/d H:i') : null;
    }

    /**
     * Get the last update date in Jalali format.
     * @return string|null
     */
    protected function getJalaliUpdatedAtAttribute(): ?string
    {
        return $this->updated_at ? Jalalian::fromCarbon($this->updated_at)->format('Y/m/d H:i') : null;
    }
    /**
     * Get the user who sent the reply (if applicable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who sent the reply (if applicable).
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

     /**
      * Determine if the reply was sent by an admin.
      */
     public function isFromAdmin(): bool
     {
         return $this->admin_id !== null;
     }
}
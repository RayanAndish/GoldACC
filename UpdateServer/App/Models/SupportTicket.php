<?php

namespace App\Models;
use Morilog\Jalali\Jalalian;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'priority',
    ];
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
     * Get the user that owns the support ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Get the replies for the support ticket.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'support_ticket_id')->orderBy('created_at'); // Order replies chronologically
    }
    public function getTranslatedStatusAttribute()
{
    return match ($this->status) {
        'open' => 'باز',
        'in_progress' => 'در حال بررسی',
        'answered' => 'پاسخ داده شده',
        'closed' => 'بسته شده',
        'resolved' => 'حل شده',
        default => $this->status,
    };
}
public function getTranslatedPriorityAttribute()
{
    return match ($this->priority) {
        'low' => 'پایین',
        'medium' => 'متوسط',
        'high' => 'بالا',
        'critical' => 'بحرانی',
        default => $this->priority,
    };
}
}

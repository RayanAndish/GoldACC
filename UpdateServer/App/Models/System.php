<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'domain',
        'ip_address',
        'customer_id',
        'status', // <-- اضافه کردن فیلد وضعیت
        'current_version', // <-- اضافه کردن نسخه فعلی (اگر لازم است)
        'hardware_id',
        'client_nonce',
        'server_nonce',
        'activation_salt',
        'client_nonce_salt',
        'server_nonce_salt',
        'hardware_id_salt',
        'request_code_salt',
        'last_activation_attempt'
    ];

    protected $hidden = [
        'activation_salt',
        'client_nonce_salt',
        'server_nonce_salt',
        'hardware_id_salt',
        'request_code_salt'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function encryptionKeys()
    {
        return $this->hasMany(EncryptionKey::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function smsSettings()
    {
        return $this->hasMany(SmsSetting::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function customerActivityLogs()
    {
        return $this->hasMany(CustomerActivityLog::class);
    }
}
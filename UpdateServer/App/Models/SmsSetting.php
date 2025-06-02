<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $fillable = ['system_id', 'is_active', 'api_key', 'sender_id', 'status'];

    public function system()
    {
        return $this->belongsTo(System::class);
    }
}
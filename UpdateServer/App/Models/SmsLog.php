<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['system_id', 'customer_id', 'to_number', 'message', 'status', 'response', 'sent_at'];

    public function system()
    {
        return $this->belongsTo(System::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
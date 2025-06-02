<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['system_id', 'customer_id', 'to_email', 'subject', 'message', 'status', 'sent_at'];

    public function system()
    {
        return $this->belongsTo(System::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
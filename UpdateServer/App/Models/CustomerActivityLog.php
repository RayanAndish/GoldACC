<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerActivityLog extends Model
{
    protected $fillable = ['customer_id', 'system_id', 'action', 'details'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function system()
    {
        return $this->belongsTo(System::class);
    }
}
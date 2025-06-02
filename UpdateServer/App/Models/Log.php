<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = ['system_id', 'type', 'message'];

    public function system()
    {
        return $this->belongsTo(System::class);
    }
}
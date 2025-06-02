<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = ['system_id', 'file_path', 'status'];

    public function system()
    {
        return $this->belongsTo(System::class);
    }
}
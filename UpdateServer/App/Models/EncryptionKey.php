<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncryptionKey extends Model
{
    use HasFactory;

    protected $fillable = ['system_id', 'key_value', 'status', 'created_at', 'updated_at'];
    public function system()
    {
        return $this->belongsTo(System::class);
    }
}

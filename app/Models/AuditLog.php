<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','action','auditable_type','auditable_id','meta','ip'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

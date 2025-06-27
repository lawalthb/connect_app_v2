<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'block_users';

    protected $fillable = [
        'user_id',
        'block_user_id',
        'reason',
        'created_by',
        'updated_by',
        'deleted_flag'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function blockedUser()
    {
        return $this->belongsTo(User::class, 'block_user_id');
    }
}

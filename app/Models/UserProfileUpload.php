<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfileUpload extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'file_name',
        'file_url',
        'file_type',
        'deleted_flag',
        'deleted_at'
    ];

    /**
     * Get the user that owns the profile upload.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

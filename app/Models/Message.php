<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'message',
        'type',
        'metadata',
        'reply_to_message_id',
        'is_edited',
        'edited_at',
        'is_deleted',
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'edited_at' => 'datetime', // Important: Ensures this is a Carbon instance
        'deleted_at' => 'datetime', // Important: Ensures this is a Carbon instance
        // created_at and updated_at are typically handled by Eloquent automatically as Carbon instances
    ];

    /**
     * Get the user who sent the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the message this is replying to.
     */
    public function replyToMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    /**
     * Get replies to this message.
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }
}

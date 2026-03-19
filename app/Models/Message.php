<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_id',
        'type', 'content', 'file_url', 'file_name',
        'file_size', 'duration', 'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function receipts()
    {
        return $this->hasMany(MessageReceipt::class);
    }

    public function getFileUrlAttribute($value): ?string
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return url('storage/' . $value);
    }

    public function getContentAttribute($value): ?string
    {
        if ($this->is_deleted) return 'This message was deleted';
        return $value;
    }
}

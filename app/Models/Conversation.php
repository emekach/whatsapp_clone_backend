<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'avatar', 'description', 'created_by', 'last_message_id'];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at', 'is_muted'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        if (str_starts_with($this->avatar, 'http')) return $this->avatar;
        return url('storage/' . $this->avatar);
    }

    public function getUnreadCountFor(User $user): int
    {
        $participant = $this->participants()
            ->where('user_id', $user->id)
            ->first();

        if (!$participant || !$participant->pivot->last_read_at) {
            return $this->messages()->where('sender_id', '!=', $user->id)->count();
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('created_at', '>', $participant->pivot->last_read_at)
            ->count();
    }
}

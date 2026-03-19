<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'phone', 'email', 'password',
        'avatar', 'about', 'last_seen', 'is_online', 'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'last_seen' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at', 'is_muted'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function statuses()
    {
        return $this->hasMany(Status::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        if (str_starts_with($this->avatar, 'http')) return $this->avatar;
        return url('storage/' . $this->avatar);
    }

    public function getLastSeenTextAttribute(): string
    {
        if ($this->is_online) return 'online';
        if (!$this->last_seen) return 'last seen recently';
        $diff = now()->diffInMinutes($this->last_seen);
        if ($diff < 1) return 'last seen just now';
        if ($diff < 60) return "last seen {$diff} minutes ago";
        if ($diff < 1440) return 'last seen today at ' . $this->last_seen->format('H:i');
        return 'last seen ' . $this->last_seen->format('d/m/Y');
    }
}

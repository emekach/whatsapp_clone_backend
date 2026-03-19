<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $fillable = ['user_id', 'type', 'content', 'file_url', 'background_color', 'expires_at'];
    protected $casts    = ['expires_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public function getFileUrlAttribute($value): ?string
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return url('storage/' . $value);
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}

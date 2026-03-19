<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReceipt extends Model
{
    protected $fillable = ['message_id', 'user_id', 'status', 'read_at'];
    protected $casts    = ['read_at' => 'datetime'];

    public function message() { return $this->belongsTo(Message::class); }
    public function user()    { return $this->belongsTo(User::class); }
}

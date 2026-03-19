<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->message->load(['sender', 'replyTo.sender']);

        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'type'            => $this->message->type,
            'content'         => $this->message->content,
            'file_url'        => $this->message->file_url,
            'file_name'       => $this->message->file_name,
            'duration'        => $this->message->duration,
            'is_deleted'      => $this->message->is_deleted,
            'created_at'      => $this->message->created_at,
            'reply_to'        => $this->message->replyTo ? [
                'id'      => $this->message->replyTo->id,
                'content' => $this->message->replyTo->content,
                'sender'  => $this->message->replyTo->sender->name,
            ] : null,
            'sender' => [
                'id'         => $this->message->sender->id,
                'name'       => $this->message->sender->name,
                'avatar_url' => $this->message->sender->avatar_url,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}

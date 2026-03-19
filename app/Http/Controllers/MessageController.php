<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /**
     * Get messages for a conversation (paginated).
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->with(['sender', 'replyTo.sender'])
            ->latest()
            ->paginate(30);

        $this->markAsRead($conversation, $user);

        return response()->json([
            'data'      => $messages->map(fn($m) => $this->formatMessage($m, $user->id)),
            'next_page' => $messages->nextPageUrl(),
        ]);
    }

    /**
     * LONG POLLING — Flutter calls this every 2 seconds.
     * Returns only NEW messages since last_id.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lastId = (int) $request->query('last_id', 0);

        $messages = Message::where('conversation_id', $conversation->id)
            ->where('id', '>', $lastId)
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('id')
            ->get();

        if ($messages->isNotEmpty()) {
            $this->markAsRead($conversation, $user);
        }

        // Get typing users from cache
        $typingUsers = $this->getTypingUsers($conversation->id, $user->id);

        // Get other participant online status
        $participants = $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->get(['users.id', 'users.is_online', 'users.last_seen']);

        return response()->json([
            'messages'     => $messages->map(fn($m) => $this->formatMessage($m, $user->id)),
            'typing_users' => $typingUsers,
            'has_new'      => $messages->isNotEmpty(),
            'participants' => $participants->map(fn($p) => [
                'id'        => $p->id,
                'is_online' => $p->is_online,
                'last_seen' => $p->last_seen_text,
            ]),
        ]);
    }

    /**
     * Send a message.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'type'        => 'required|in:text,image,video,audio,document',
            'content'     => 'required_if:type,text|nullable|string|max:5000',
            'file'        => 'required_unless:type,text|nullable|file|max:51200',
            'reply_to_id' => 'nullable|exists:messages,id',
        ]);

        $fileUrl  = null;
        $fileName = null;
        $fileSize = null;
        $duration = null;

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $folder   = match($request->type) {
                'image'    => 'chat-images',
                'video'    => 'chat-videos',
                'audio'    => 'chat-audio',
                'document' => 'chat-documents',
                default    => 'chat-files',
            };
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $destDir  = public_path("storage/{$folder}");
            if (!file_exists($destDir)) mkdir($destDir, 0755, true);
            $file->move($destDir, $filename);

            $fileUrl  = "{$folder}/{$filename}";
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $duration = $request->duration ?? null;
        }

        $message = DB::transaction(function () use (
            $request, $conversation, $user,
            $fileUrl, $fileName, $fileSize, $duration
        ) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'type'            => $request->type,
                'content'         => $request->content,
                'reply_to_id'     => $request->reply_to_id,
                'file_url'        => $fileUrl,
                'file_name'       => $fileName,
                'file_size'       => $fileSize,
                'duration'        => $duration,
            ]);

            $conversation->update([
                'last_message_id' => $msg->id,
                'updated_at'      => now(),
            ]);

            return $msg;
        });

        $message->load(['sender', 'replyTo.sender']);

        return response()->json([
            'message' => $this->formatMessage($message, $user->id),
        ], 201);
    }

    /**
     * Delete a message (soft delete).
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $message->update(['is_deleted' => true, 'content' => null]);

        return response()->json(['message' => 'Message deleted']);
    }

    /**
     * Mark messages as read.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->markAsRead($conversation, $request->user());
        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Typing indicator — stored in Laravel cache for 4 seconds.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['is_typing' => 'required|boolean']);
        $user = $request->user();
        $key  = "typing:{$conversation->id}:{$user->id}";

        if ($request->is_typing) {
            cache()->put($key, [
                'user_id'   => $user->id,
                'user_name' => $user->name,
            ], now()->addSeconds(4));
        } else {
            cache()->forget($key);
        }

        return response()->json(['message' => 'ok']);
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function markAsRead(Conversation $conversation, $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    private function getTypingUsers(int $conversationId, int $myUserId): array
    {
        $typing = [];
        $participants = DB::table('conversation_participants')
            ->where('conversation_id', $conversationId)
            ->where('user_id', '!=', $myUserId)
            ->pluck('user_id');

        foreach ($participants as $userId) {
            $cached = cache()->get("typing:{$conversationId}:{$userId}");
            if ($cached) {
                $typing[] = $cached;
            }
        }

        return $typing;
    }

    private function formatMessage(Message $message, int $authUserId): array
    {
        return [
            'id'         => $message->id,
            'type'       => $message->type,
            'content'    => $message->is_deleted
                ? 'This message was deleted'
                : $message->content,
            'file_url'   => $message->file_url,
            'file_name'  => $message->file_name,
            'file_size'  => $message->file_size,
            'duration'   => $message->duration,
            'is_deleted' => $message->is_deleted,
            'is_mine'    => $message->sender_id === $authUserId,
            'created_at' => $message->created_at,
            'reply_to'   => $message->replyTo ? [
                'id'      => $message->replyTo->id,
                'content' => $message->replyTo->content,
                'sender'  => $message->replyTo->sender?->name,
            ] : null,
            'sender' => [
                'id'         => $message->sender->id,
                'name'       => $message->sender->name,
                'avatar_url' => $message->sender->avatar_url,
            ],
        ];
    }
}

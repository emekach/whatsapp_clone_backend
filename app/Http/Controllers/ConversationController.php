<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * List all conversations for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['lastMessage.sender', 'participants'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($c) => $this->formatConversation($c, $user));

        return response()->json(['data' => $conversations]);
    }

    /**
     * Start or get a private conversation with another user.
     */
    public function startPrivate(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user   = $request->user();
        $other  = User::findOrFail($request->user_id);

        // Check if private conversation already exists
        $existing = Conversation::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $other->id))
            ->with(['lastMessage.sender', 'participants'])
            ->first();

        if ($existing) {
            return response()->json(['conversation' => $this->formatConversation($existing, $user)]);
        }

        $conversation = DB::transaction(function () use ($user, $other) {
            $conv = Conversation::create(['type' => 'private']);
            $conv->participants()->attach([
                $user->id  => ['role' => 'member'],
                $other->id => ['role' => 'member'],
            ]);
            return $conv;
        });

        $conversation->load(['participants']);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user),
        ], 201);
    }

    /**
     * Create a group conversation.
     */
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
            'description' => 'nullable|string|max:200',
        ]);

        $user = $request->user();

        $conversation = DB::transaction(function () use ($request, $user) {
            $conv = Conversation::create([
                'type'        => 'group',
                'name'        => $request->name,
                'description' => $request->description,
                'created_by'  => $user->id,
            ]);

            $participants = collect($request->participant_ids)
                ->mapWithKeys(fn($id) => [$id => ['role' => 'member']]);
            $participants[$user->id] = ['role' => 'admin'];

            $conv->participants()->attach($participants);
            return $conv;
        });

        $conversation->load('participants');

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user),
        ], 201);
    }

    /**
     * Get conversation details.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation->load(['participants', 'lastMessage.sender']);

        return response()->json(['conversation' => $this->formatConversation($conversation, $user)]);
    }

    /**
     * Update group info.
     */
    public function updateGroup(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $participant = $conversation->participants()->where('user_id', $user->id)->first();
        if (!$participant || $participant->pivot->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update group'], 403);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:200',
        ]);

        $conversation->update($data);

        return response()->json(['conversation' => $this->formatConversation($conversation->fresh(), $user)]);
    }

    /**
     * Update group avatar.
     */
    public function updateGroupAvatar(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|max:5120']);

        $filename = \Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension();
        $destDir  = public_path('storage/group-avatars');
        if (!file_exists($destDir)) mkdir($destDir, 0755, true);
        $request->file('avatar')->move($destDir, $filename);

        if ($conversation->avatar) {
            @unlink(public_path('storage/group-avatars/' . basename($conversation->avatar)));
        }

        $conversation->update(['avatar' => 'group-avatars/' . $filename]);

        return response()->json(['avatar_url' => $conversation->fresh()->avatar_url]);
    }

    /**
     * Add participants to group.
     */
    public function addParticipants(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['user_ids' => 'required|array', 'user_ids.*' => 'exists:users,id']);

        foreach ($request->user_ids as $userId) {
            $conversation->participants()->syncWithoutDetaching([
                $userId => ['role' => 'member'],
            ]);
        }

        return response()->json(['message' => 'Participants added']);
    }

    /**
     * Leave or remove from group.
     */
    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $conversation->participants()->detach($user->id);

        return response()->json(['message' => 'Left group']);
    }

    // ── Format helpers ───────────────────────────────────────────────

    private function formatConversation(Conversation $conv, User $authUser): array
    {
        $isGroup   = $conv->type === 'group';
        $otherUser = null;

        if (!$isGroup) {
            $otherUser = $conv->participants->firstWhere('id', '!=', $authUser->id);
        }

        return [
            'id'           => $conv->id,
            'type'         => $conv->type,
            'name'         => $isGroup ? $conv->name : $otherUser?->name,
            'avatar_url'   => $isGroup ? $conv->avatar_url : $otherUser?->avatar_url,
            'description'  => $conv->description,
            'is_online'    => !$isGroup && ($otherUser?->is_online ?? false),
            'last_seen'    => !$isGroup ? $otherUser?->last_seen_text : null,
            'other_user_id'=> !$isGroup ? $otherUser?->id : null,
            'unread_count' => $conv->getUnreadCountFor($authUser),
            'participants' => $conv->participants->map(fn($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'avatar_url' => $p->avatar_url,
                'role'       => $p->pivot->role,
                'is_online'  => $p->is_online,
            ]),
            'last_message' => $conv->lastMessage ? [
                'id'         => $conv->lastMessage->id,
                'type'       => $conv->lastMessage->type,
                'content'    => $conv->lastMessage->is_deleted
                    ? 'This message was deleted'
                    : ($conv->lastMessage->type === 'text'
                        ? $conv->lastMessage->content
                        : ucfirst($conv->lastMessage->type)),
                'sender_id'  => $conv->lastMessage->sender_id,
                'sender_name'=> $conv->lastMessage->sender?->name,
                'created_at' => $conv->lastMessage->created_at,
                'is_mine'    => $conv->lastMessage->sender_id === $authUser->id,
            ] : null,
            'updated_at' => $conv->updated_at,
        ];
    }
}

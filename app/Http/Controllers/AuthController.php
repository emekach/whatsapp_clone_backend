<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Events\UserOnlineStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:50',
            'phone'    => 'required|string|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $user  = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account created',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'    => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid credentials.'],
            ]);
        }

        $user->tokens()->where('name', 'auth_token')->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Mark online
        $user->update(['is_online' => true, 'last_seen' => now()]);
        broadcast(new UserOnlineStatus($user, true))->toOthers();

        return response()->json([
            'message' => 'Logged in',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['is_online' => false, 'last_seen' => now()]);
        broadcast(new UserOnlineStatus($user, false))->toOthers();
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'  => 'sometimes|string|max:50',
            'about' => 'sometimes|string|max:139',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
        ]);
        $user->update($data);

        return response()->json(['user' => $this->formatUser($user->fresh())]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|max:5120']);
        $user = $request->user();

        $filename = \Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension();
        $destDir  = public_path('storage/avatars');
        if (!file_exists($destDir)) mkdir($destDir, 0755, true);
        $request->file('avatar')->move($destDir, $filename);

        if ($user->avatar) @unlink(public_path('storage/avatars/' . basename($user->avatar)));
        $user->update(['avatar' => 'avatars/' . $filename]);

        return response()->json(['avatar_url' => $user->fresh()->avatar_url]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated']);
    }

    public function ping(Request $request): JsonResponse
    {
        $request->user()->update(['is_online' => true, 'last_seen' => now()]);

        return response()->json(['message' => 'pong']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'phone'      => $user->phone,
            'email'      => $user->email,
            'avatar_url' => $user->avatar_url,
            'about'      => $user->about,
            'is_online'  => $user->is_online,
            'last_seen'  => $user->last_seen,
        ];
    }
}

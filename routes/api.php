<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// ── Public ───────────────────────────────────────────────────────────────
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// ── Authenticated ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/me',          [AuthController::class, 'me']);
    Route::post('/auth/logout',     [AuthController::class, 'logout']);
    Route::put('/auth/profile',     [AuthController::class, 'updateProfile']);
    Route::post('/auth/avatar',     [AuthController::class, 'updateAvatar']);
    Route::post('/auth/fcm-token',  [AuthController::class, 'updateFcmToken']);
    Route::post('/auth/ping',       [AuthController::class, 'ping']);

    // Conversations
    Route::get('/conversations',                              [ConversationController::class, 'index']);
    Route::post('/conversations/private',                     [ConversationController::class, 'startPrivate']);
    Route::post('/conversations/group',                       [ConversationController::class, 'createGroup']);
    Route::get('/conversations/{conversation}',               [ConversationController::class, 'show']);
    Route::put('/conversations/{conversation}',               [ConversationController::class, 'updateGroup']);
    Route::post('/conversations/{conversation}/avatar',       [ConversationController::class, 'updateGroupAvatar']);
    Route::post('/conversations/{conversation}/participants', [ConversationController::class, 'addParticipants']);
    Route::delete('/conversations/{conversation}/leave',      [ConversationController::class, 'leave']);

    // Messages
    Route::get('/conversations/{conversation}/messages',       [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages',      [MessageController::class, 'store']);
    Route::post('/conversations/{conversation}/messages/read', [MessageController::class, 'markRead']);
    Route::post('/conversations/{conversation}/typing',        [MessageController::class, 'typing']);
    Route::delete('/messages/{message}',                       [MessageController::class, 'destroy']);

    // ── LONG POLLING endpoint ────────────────────────────────────────
    // Flutter calls GET /conversations/{id}/poll?last_id=123 every 2 seconds
    Route::get('/conversations/{conversation}/poll', [MessageController::class, 'poll']);

    // Contacts
    Route::get('/contacts',               [ContactController::class, 'index']);
    Route::get('/contacts/search',        [ContactController::class, 'search']);
    Route::post('/contacts',              [ContactController::class, 'add']);
    Route::post('/contacts/{id}/block',   [ContactController::class, 'block']);
    Route::post('/contacts/{id}/unblock', [ContactController::class, 'unblock']);

    // Statuses
    Route::get('/statuses',             [StatusController::class, 'index']);
    Route::post('/statuses',            [StatusController::class, 'store']);
    Route::delete('/statuses/{status}', [StatusController::class, 'destroy']);
});

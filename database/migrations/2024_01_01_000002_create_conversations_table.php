<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations (both private and group)
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'group'])->default('private');
            $table->string('name')->nullable();         // group name
            $table->string('avatar')->nullable();       // group avatar
            $table->text('description')->nullable();    // group description
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_message_id')->nullable();
            $table->timestamps();
        });

        // Conversation participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        // Messages
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->enum('type', ['text', 'image', 'video', 'audio', 'document', 'location'])->default('text');
            $table->text('content')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedInteger('duration')->nullable(); // audio/video duration in seconds
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // Message receipts (read/delivered status per user)
        Schema::create('message_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['delivered', 'read'])->default('delivered');
            $table->timestamp('read_at')->nullable();
            $table->unique(['message_id', 'user_id']);
        });

        // Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('users')->onDelete('cascade');
            $table->string('nickname')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'contact_id']);
        });

        // Status updates (stories)
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['text', 'image', 'video'])->default('text');
            $table->text('content')->nullable();
            $table->string('file_url')->nullable();
            $table->string('background_color')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Now add the foreign key for last_message_id
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('message_receipts');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Direct messaging between members of one school.
 *
 * A conversation is a pair of users, stored once with the lower id in
 * `participant_a_id`. Keeping the pair ordered means "A talks to B" and "B
 * talks to A" resolve to the same row instead of two threads that each show
 * half the history — the unique index makes that impossible to get wrong even
 * if a caller forgets to normalise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->foreignId('participant_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_b_id')->constrained('users')->cascadeOnDelete();

            // Denormalised for the conversation list: sorting by the newest
            // message should not require joining every message row.
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['school_id', 'participant_a_id', 'participant_b_id'],
                'conversations_unique_pair',
            );
            $table->index(['participant_a_id', 'last_message_at']);
            $table->index(['participant_b_id', 'last_message_at']);
        });

        // A pair must be ordered, so the unique index above can hold.
        DB::statement('ALTER TABLE conversations ADD CONSTRAINT conversations_ordered_pair CHECK (participant_a_id < participant_b_id)');

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            $table->text('body');

            /*
             * Null until the other participant opens the thread. There is no
             * per-recipient row because a conversation has exactly two people
             * and the sender has, by definition, read their own message.
             */
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};

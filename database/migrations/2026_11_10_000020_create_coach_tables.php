<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            // History replay window (see App\Services\Ai\Coach\ConversationHistory):
            // messages before the anchor are no longer sent to the model, and reasoning
            // blocks of messages up to `thinking_stripped_upto_id` are dropped on replay.
            $table->unsignedBigInteger('history_anchor_id')->nullable();
            $table->unsignedBigInteger('thinking_stripped_upto_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('coach_conversations')->cascadeOnDelete();
            $table->string('role', 12);            // user | assistant | note (app-generated fact)
            // JSON list of API content blocks, stored as TEXT on purpose: a native JSON column
            // (MySQL) re-orders object keys, and the blocks must be replayed exactly as received.
            $table->longText('content');
            $table->json('meta')->nullable();      // kind, display text, usage, stop_reason ...
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('coach_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('coach_conversations')->nullOnDelete();
            $table->string('type', 30);            // App\Services\Ai\Proposals\ProposalType
            $table->json('payload');               // validated input (ids, quantities, units)
            $table->json('preview');               // engine-computed preview shown to the user
            $table->string('status', 15)->default('pending'); // App\Services\Ai\Proposals\ProposalStatus
            $table->json('result')->nullable();    // record ids written, or the error
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_proposals');
        Schema::dropIfExists('coach_messages');
        Schema::dropIfExists('coach_conversations');
    }
};

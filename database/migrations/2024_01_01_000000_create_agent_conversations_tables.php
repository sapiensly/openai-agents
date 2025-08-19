<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('summary_updated_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'last_message_at']);
            $table->index(['agent_id', 'created_at']);
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')
                ->constrained('agent_conversations')
                ->cascadeOnDelete();
            $table->enum('role', ['system', 'user', 'assistant', 'developer', 'tool']);
            $table->text('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_conversations');
    }
};

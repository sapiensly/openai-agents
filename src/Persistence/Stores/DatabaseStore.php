<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Models\Conversation;
use Sapiensly\OpenaiAgents\Persistence\Models\Message;

class DatabaseStore implements ConversationStore
{
    protected string $cachePrefix = 'agent:conv:';
    protected int $cacheTtl = 3600;

    public function __construct()
    {
        $this->cacheTtl = (int) (config('agent-persistence.stores.database.cache.ttl') ?? 3600);
    }

    public function findOrCreate(string $id, ?array $metadata = []): array
    {
        $conversation = Conversation::firstOrCreate(
            ['id' => $id],
            [
                'agent_id' => $metadata['agent_id'] ?? null,
                'user_id' => auth()->id() ?? null,
                'metadata' => $metadata ?? [],
            ]
        );
        return $conversation->toArray();
    }

    public function addMessage(string $conversationId, array $message): void
    {
        DB::transaction(function () use ($conversationId, $message) {
            Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'role' => (string) ($message['role'] ?? ''),
                'content' => (string) ($message['content'] ?? ''),
                'token_count' => $message['token_count'] ?? null,
                'metadata' => $message['metadata'] ?? null,
                'created_at' => now(),
            ]);

            Conversation::where('id', $conversationId)->update([
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1'),
                'total_tokens' => DB::raw('total_tokens + ' . (int) ($message['token_count'] ?? 0)),
            ]);
        });

        $this->flushCache($conversationId);
    }

    public function getRecentMessages(string $conversationId, int $limit = 20): array
    {
        $key = $this->cachePrefix . $conversationId . ':messages:' . $limit;
        $useTags = (bool) (config('agent-persistence.stores.database.cache.tags') ?? true);
        $remember = function () use ($conversationId, $limit) {
            return Message::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values()
                ->map(function ($m) {
                    return [
                        'role' => $m->role,
                        'content' => $m->content,
                        'token_count' => $m->token_count,
                        'metadata' => $m->metadata,
                        'created_at' => $m->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        };
        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['conversation:' . $conversationId])->remember($key, $this->cacheTtl, $remember);
        }
        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    public function getSummary(string $conversationId): ?string
    {
        $key = $this->cachePrefix . $conversationId . ':summary';
        $useTags = (bool) (config('agent-persistence.stores.database.cache.tags') ?? true);
        $remember = function () use ($conversationId) {
            return Conversation::find($conversationId)?->summary;
        };
        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['conversation:' . $conversationId])->remember($key, $this->cacheTtl, $remember);
        }
        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    public function updateSummary(string $conversationId, string $summary): void
    {
        Conversation::where('id', $conversationId)->update([
            'summary' => $summary,
            'summary_updated_at' => now(),
        ]);
        $this->flushCache($conversationId);
    }

    public function delete(string $conversationId): void
    {
        DB::transaction(function () use ($conversationId) {
            Message::where('conversation_id', $conversationId)->delete();
            Conversation::where('id', $conversationId)->delete();
        });
        $this->flushCache($conversationId);
    }

    protected function flushCache(string $conversationId): void
    {
        $useTags = (bool) (config('agent-persistence.stores.database.cache.tags') ?? true);
        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['conversation:' . $conversationId])->flush();
        } else {
            Cache::forget($this->cachePrefix . $conversationId . ':summary');
        }
    }
}

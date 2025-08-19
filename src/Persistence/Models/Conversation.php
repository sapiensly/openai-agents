<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $table = 'agent_conversations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'agent_id', 'user_id', 'title', 'summary', 'metadata', 'last_message_at',
        'summary_updated_at', 'message_count', 'total_tokens',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'summary_updated_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}

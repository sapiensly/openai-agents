<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentDefinition extends Model
{
    use SoftDeletes;

    protected $table = 'agent_definitions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'options', 'instructions', 'tools', 'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'tools' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Find agent definition by name.
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Get all agent definition names.
     */
    public static function getAllNames(): array
    {
        return static::pluck('name')->toArray();
    }
} 
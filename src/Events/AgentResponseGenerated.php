<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentResponseGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $agentId,
        public string $userMessage,
        public string $response,
        public array $metadata = []
    ) {}
}


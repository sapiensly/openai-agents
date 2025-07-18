<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Listeners;

use Illuminate\Support\Facades\Log;
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;

class LogAgentAnalytics
{
    public function handle(AgentResponseGenerated $event): void
    {
        $logData = [
            'agent_id' => $event->agentId,
            'user_message_length' => strlen($event->userMessage),
            'response_length' => strlen($event->response),
            'success' => $event->metadata['success'] ?? true,
        ];

        // Add performance metrics if available
        if (isset($event->metadata['response_time'])) {
            $logData['response_time'] = $event->metadata['response_time'];
        }

        // Add model information
        if (isset($event->metadata['model'])) {
            $logData['model'] = $event->metadata['model'];
        }

        // Add usage information if available
        if (isset($event->metadata['usage'])) {
            $logData['usage'] = $event->metadata['usage'];
        }

        // Add tools information
        if (isset($event->metadata['tools_used'])) {
            $logData['tools_used'] = $event->metadata['tools_used'];
        }

        // Add API method information
        if (isset($event->metadata['api_method'])) {
            $logData['api_method'] = $event->metadata['api_method'];
        }

        // Add error information if it's an error event
        if (!($event->metadata['success'] ?? true)) {
            $logData['error'] = $event->metadata['error'] ?? 'Unknown error';
            $logData['error_type'] = $event->metadata['error_type'] ?? 'UnknownException';
        }

        // Simple logging based on success/failure
        if ($logData['success']) {
            Log::info('Agent Response Analytics Log', $logData);
        } else {
            Log::error('Agent Response Analytics Log [Error!]', $logData);
        }
    }
}

<?php

namespace Sapiensly\OpenaiAgents\Helpers;

use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Sapiensly\OpenaiAgents\Tools\ToolRegistry;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Tracing\Tracing;

class AgentHelpers
{
    /**
     * Get the default agent from the manager
     */
    public static function getDefaultAgent(): Agent
    {
        return app(AgentManager::class)->agent();
    }

    /**
     * Create a new agent with given name and instructions
     */
    public static function createAgent(string $name, string $instructions): Agent
    {
        return app(AgentManager::class)->agent([], $instructions);
    }

    /**
     * Get a runner instance
     */
    public static function getRunner(Agent $agent = null): Runner
    {
        return app(AgentManager::class)->runner($agent);
    }

    /**
     * Run an agent with a message and get the result
     */
    public static function runAgent(Agent $agent, string $message): mixed
    {
        $runner = app(Runner::class);
        return $runner->run($agent, $message);
    }

    /**
     * Run an agent with streaming and get chunks
     */
    public static function streamAgent(Agent $agent, string $message): \Generator
    {
        $runner = app(Runner::class);
        return $runner->runStreamed($agent, $message);
    }

    /**
     * Get all available tools
     */
    public static function getTools(): array
    {
        return app(ToolRegistry::class)->getAllTools();
    }

    /**
     * Get a specific tool by name
     */
    public static function getTool(string $name): mixed
    {
        return app(ToolRegistry::class)->getTool($name);
    }

    /**
     * Get the handoff orchestrator
     */
    public static function getHandoffOrchestrator(): HandoffOrchestrator
    {
        return app(HandoffOrchestrator::class);
    }

    /**
     * Get the tracing instance
     */
    public static function getTracing(): Tracing
    {
        return app(Tracing::class);
    }

    /**
     * Create a simple test agent for quick testing
     */
    public static function createTestAgent(): Agent
    {
        return new Agent(
            'TestAgent',
            'You are a helpful test agent. Keep responses concise and engaging.'
        );
    }

    /**
     * Quick test function to verify everything works
     */
    public static function quickTest(): string
    {
        try {
            $agent = self::createTestAgent();
            $result = self::runAgent($agent, 'Say hello in a creative way');
            return "✅ Test successful! Response: " . $result->final_output;
        } catch (\Exception $e) {
            return "❌ Test failed: " . $e->getMessage();
        }
    }

    /**
     * Test streaming functionality
     */
    public static function testStreaming(): void
    {
        $agent = self::createTestAgent();
        echo "🔄 Testing streaming...\n";
        
        foreach (self::streamAgent($agent, 'Write a short story about a robot') as $chunk) {
            echo $chunk;
        }
        echo "\n✅ Streaming test completed!\n";
    }

    /**
     * Show available commands and examples
     */
    public static function help(): void
    {
        echo "🤖 Agent Helpers - Available Commands:\n\n";
        echo "📝 Basic Usage:\n";
        echo "  \$agent = AgentHelpers::createAgent('Name', 'Instructions');\n";
        echo "  \$result = AgentHelpers::runAgent(\$agent, 'Your message');\n";
        echo "  echo \$result->final_output;\n\n";
        
        echo "🔄 Streaming:\n";
        echo "  foreach (AgentHelpers::streamAgent(\$agent, 'Message') as \$chunk) {\n";
        echo "      echo \$chunk;\n";
        echo "  }\n\n";
        
        echo "🛠️ Tools:\n";
        echo "  \$tools = AgentHelpers::getTools();\n";
        echo "  \$tool = AgentHelpers::getTool('tool_name');\n\n";
        
        echo "🧪 Testing:\n";
        echo "  AgentHelpers::quickTest();\n";
        echo "  AgentHelpers::testStreaming();\n\n";
        
        echo "📊 Advanced:\n";
        echo "  \$handoff = AgentHelpers::getHandoffOrchestrator();\n";
        echo "  \$tracing = AgentHelpers::getTracing();\n";
    }
} 
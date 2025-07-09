<?php

/**
 * AgentTinker Command
 * 
 * Purpose: Provides an interactive development environment for testing and experimenting
 * with OpenAI agents. This command starts Laravel's Tinker with pre-loaded agent
 * variables and helpers, making it easy to test agent functionality interactively.
 * 
 * Features:
 * - Pre-loads agent instances ($agent, $runner, $agentManager, $helpers)
 * - Provides quick access to common agent operations
 * - Supports both interactive mode and one-time code execution
 * - Includes helpful examples and documentation
 * 
 * Usage:
 * - Interactive mode: php artisan agents:tinker
 * - Execute specific code: php artisan agents:tinker --execute="echo $agent->chat('Hello')"
 * 
 * Available variables in Tinker:
 * - $agent: Default agent instance
 * - $runner: Runner instance for tool usage
 * - $agentManager: Agent manager for creating/managing agents
 * - $helpers: AgentHelpers class with utility methods
 * 
 * Quick examples:
 * - $helpers::quickTest() - Run a quick agent test
 * - $helpers::testStreaming() - Test streaming functionality
 * - $helpers::help() - Show available helper methods
 * - $runner->run("Calculate 2+2") - Use tools with runner
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Helpers\AgentHelpers;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;

class AgentTinker extends Command
{
    protected $signature = 'agents:tinker {--execute= : Execute specific code and exit}';
    protected $description = 'Start Tinker with agent helpers pre-loaded';

    public function handle()
    {
        $this->info('🤖 Agent Tinker - Interactive Agent Development Environment');
        $this->newLine();
        
        // Show available variables and helpers
        $this->showAvailableHelpers();
        
        if ($execute = $this->option('execute')) {
            $this->executeCode($execute);
            return;
        }
        
        $this->info('🚀 Starting Tinker with agent helpers...');
        $this->newLine();
        
                    // Create a temporary script that defines the variables
        $script = $this->createTinkerScript();
        $tempFile = tempnam(sys_get_temp_dir(), 'agent_tinker_');
        file_put_contents($tempFile, $script);
        
        $this->info('--- Code to be executed in Tinker ---');
        $this->line($script);
        $this->info('----------------------------------------');
        
                    // Execute Tinker with the script
        $this->call('tinker', ['--execute' => $script]);
        
        // Limpiar
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
    
    private function showAvailableHelpers(): void
    {
        $this->info('📋 Available Variables:');
        $this->line('  • $agent - Default agent instance');
        $this->line('  • $runner - Runner instance');
        $this->line('  • $agentManager - Agent manager');
        $this->line('  • $helpers - AgentHelpers class');
        $this->newLine();
        
        $this->info('🛠️ Quick Examples:');
        $this->line('  • $result = $runner->run($agent, "Hello")');
        $this->line('  • $helpers::quickTest()');
        $this->line('  • $helpers::testStreaming()');
        $this->line('  • $helpers::help()');
        $this->newLine();
    }
    
    private function createTinkerScript(): string
    {
        $script = "// Agent Tinker - Pre-loaded Variables\n";
        $script .= "echo '🤖 Agent Tinker Ready!\\n';\n";
        $script .= "echo 'Available variables: \$agent, \$runner, \$agentManager, \$helpers\\n';\n";
        $script .= "echo 'Type \$helpers::help() for examples\\n\\n';\n\n";
        
                    // Define variables using app()
        $script .= "\$agentManager = app('Sapiensly\\\\OpenaiAgents\\\\AgentManager');\n";
        $script .= "\$agent = \$agentManager->agent();\n";
        $script .= "\$runner = \$agentManager->runner(\$agent);\n";
        $script .= "\$helpers = 'Sapiensly\\\\OpenaiAgents\\\\Helpers\\\\AgentHelpers';\n\n";
        
        return $script;
    }
    
    private function executeCode(string $code): void
    {
        $this->info('🔧 Executing code in agent context...');
        $this->newLine();
        
        try {
            $script = $this->createTinkerScript() . $code;
            $this->call('tinker', ['--execute' => $script]);
        } catch (\Exception $e) {
            $this->error('❌ Error executing code: ' . $e->getMessage());
        }
    }
} 
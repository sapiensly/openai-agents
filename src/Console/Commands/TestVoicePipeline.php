<?php

/**
 * TestVoicePipeline - Voice Pipeline End-to-End Testing
 *
 * Purpose: Tests the complete voice pipeline from speech-to-text (STT), through
 * agent processing, to text-to-speech (TTS). This command demonstrates audio
 * input, agent response, and audio output in a single workflow.
 *
 * Features Tested:
 * - Audio file input and output
 * - Speech-to-text (STT) transcription
 * - Agent chat processing
 * - Text-to-speech (TTS) synthesis
 * - File management and storage
 * - Step-by-step process reporting
 * - Error handling and debugging
 *
 * Usage:
 * - Basic: php artisan agent:test-voice-pipeline
 * - Custom input: php artisan agent:test-voice-pipeline --input=path/to/input.wav
 * - Custom output: php artisan agent:test-voice-pipeline --output=path/to/output.mp3
 * - Custom system: php artisan agent:test-voice-pipeline --system="You are a helpful assistant."
 *
 * Test Scenarios:
 * 1. Audio file input and STT transcription
 * 2. Agent chat processing of transcribed text
 * 3. TTS synthesis of agent response
 * 4. File management and output
 * 5. Error handling and debugging
 *
 * Voice Pipeline Steps:
 * - Input audio file (WAV)
 * - Transcribe to text (STT)
 * - Agent processes text
 * - Synthesize response to audio (TTS)
 * - Output audio file (MP3)
 *
 * Error Handling:
 * - Input file validation
 * - API key and agent errors
 * - File and directory management
 *
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\VoicePipeline;
use OpenAI\Factory;

class TestVoicePipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-voice-pipeline 
                            {--input=storage/app/audio/input.wav : Input audio file path}
                            {--output=storage/app/audio/reply.mp3 : Output audio file path}
                            {--system=You are a helpful assistant. : System prompt for the agent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete voice pipeline: STT → Agent → TTS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🎤 Testing Voice Pipeline: STT → Agent → TTS');
        $this->newLine();

        $inputFile = $this->option('input');
        $outputFile = $this->option('output');
        $systemPrompt = $this->option('system');

        // Ensure storage directory exists
        $storageDir = dirname($inputFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
            $this->line("📁 Created storage directory: {$storageDir}");
        }

        // Check if input file exists
        if (!file_exists($inputFile)) {
            $this->error("❌ Input file not found: {$inputFile}");
            $this->line("💡 Create a test audio file with: cd packages/sapiensly/openai-agents/tools && python3 create_test_audio.py");
            $this->line("💡 Or specify with --input=path/to/audio.wav");
            return Command::FAILURE;
        }

        try {
            // Step 1: Create agent and pipeline
            $this->info('🤖 Creating agent and voice pipeline...');
            $manager = app(AgentManager::class);
            $agent = $manager->agent(null, $systemPrompt);
            $client = (new Factory())->withApiKey(config('agents.api_key'))->make();
            $pipeline = new VoicePipeline($client, $agent);

            // Step 2: Transcribe audio to text
            $this->info('📝 Transcribing audio to text...');
            $transcribedText = $pipeline->transcribe($inputFile);
            $this->line("✅ Transcribed text: \"{$transcribedText}\"");

            // Step 3: Send text to agent
            $this->info('💬 Sending text to agent...');
            $agentResponse = $agent->chat($transcribedText);
            $this->line("✅ Agent response: \"{$agentResponse}\"");

            // Step 4: Convert agent response to speech
            $this->info('🔊 Converting response to speech...');
            $audioContent = $pipeline->speak($agentResponse);

            // Step 5: Save audio file
            $this->info('💾 Saving audio file...');
            
            // Ensure output directory exists
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            file_put_contents($outputFile, $audioContent);
            $this->line("✅ Audio saved to: {$outputFile}");

            // Step 6: Display summary
            $this->newLine();
            $this->info('🎉 Voice pipeline test completed successfully!');
            $this->table(
                ['Step', 'Result'],
                [
                    ['Input Audio', $inputFile],
                    ['Transcribed Text', $transcribedText],
                    ['Agent Response', $agentResponse],
                    ['Output Audio', $outputFile],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Voice pipeline test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
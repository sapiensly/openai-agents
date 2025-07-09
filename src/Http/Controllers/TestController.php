<?php

namespace Sapiensly\OpenaiAgents\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;

class TestController extends Controller
{
    /**
     * Display the SSE testing page
     */
    public function sseTest()
    {
        // Check if testing is enabled
        if (!Config::get('agents.testing.enabled', false)) {
            abort(404, 'Testing tools are not enabled');
        }

        return view('sapiensly-openai-agents::test.sse');
    }

    /**
     * Handle SSE streaming test endpoint
     */
    public function chatStream(Request $request)
    {
        // Check if testing is enabled
        if (!Config::get('agents.testing.enabled', false)) {
            abort(404, 'Testing tools are not enabled');
        }

        $message = $request->input('message', 'Tell me a short story about a robot learning to paint');
        $system = $request->input('system', 'You are a helpful AI assistant. Keep responses concise and engaging.');

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Send initial connection event
        echo "data: " . json_encode([
            'type' => 'connected',
            'message' => 'SSE connection established',
            'timestamp' => now()->toISOString()
        ]) . "\n\n";

        // Flush the output buffer
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();

        // Simulate streaming response
        $response = "Once upon a time, there was a curious robot named Pixel who lived in a digital art studio. Pixel had always been fascinated by colors and shapes, but had never tried to create art itself. One day, it decided to learn how to paint.\n\n";
        
        $words = explode(' ', $response);
        $chunkCount = 0;
        $totalChars = 0;

        foreach ($words as $index => $word) {
            // Add some delay to simulate real streaming
            usleep(rand(50000, 150000)); // 50-150ms delay

            $chunk = $word . ($index < count($words) - 1 ? ' ' : '');
            
            echo "data: " . json_encode([
                'type' => 'chunk',
                'chunk' => $chunk,
                'timestamp' => now()->toISOString()
            ]) . "\n\n";

            $chunkCount++;
            $totalChars += strlen($chunk);

            // Flush after each chunk
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }

        // Send completion event
        echo "data: " . json_encode([
            'type' => 'done',
            'message' => 'Stream completed',
            'stats' => [
                'chunks' => $chunkCount,
                'total_chars' => $totalChars,
                'duration_ms' => 0, // Could calculate actual duration
            ],
            'timestamp' => now()->toISOString()
        ]) . "\n\n";

        // Flush final output
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
    }
} 
<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use OpenAI\Contracts\ClientContract;

class VoicePipeline
{
    protected ClientContract $client;
    protected Agent $agent;

    public function __construct(ClientContract $client, Agent $agent)
    {
        $this->client = $client;
        $this->agent = $agent;
    }

    public function transcribe(string $file, array $options = []): string
    {
        $params = array_merge([
            'model' => 'whisper-1',
            'file' => fopen($file, 'r'),
            'response_format' => 'text',
        ], $options);

        $response = $this->client->audio()->transcribe($params);
        return $response->text;
    }

    public function speak(string $text, array $options = []): string
    {
        return $this->agent->speak($text, $options);
    }

    public function run(string $file, array $options = []): string
    {
        $text = $this->transcribe($file, $options['transcribe'] ?? []);
        $reply = $this->agent->chat($text);
        return $this->speak($reply, $options['speak'] ?? []);
    }
}

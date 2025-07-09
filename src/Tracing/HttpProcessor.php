<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing;

use Symfony\Component\HttpClient\HttpClient;

class HttpProcessor
{
    protected string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function __invoke(array $record): void
    {
        $client = HttpClient::create();
        $client->request('POST', $this->url, ['json' => $record]);
    }
}

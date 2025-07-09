<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing;

class Tracing
{
    /**
     * @var array<int, callable>
     */
    protected array $processors = [];

    public function __construct(array $processors = [])
    {
        foreach ($processors as $processor) {
            if (is_callable($processor)) {
                $this->processors[] = $processor;
            }
        }
    }

    public function startSpan(string $name, array $attributes = []): string
    {
        $id = uniqid('span_', true);
        $this->dispatch(['type' => 'start_span', 'id' => $id, 'name' => $name, 'attributes' => $attributes]);
        return $id;
    }

    public function endSpan(string $id): void
    {
        $this->dispatch(['type' => 'end_span', 'id' => $id]);
    }

    public function recordEvent(string $id, array $data): void
    {
        $this->dispatch(['type' => 'event', 'id' => $id] + $data);
    }

    protected function dispatch(array $record): void
    {
        foreach ($this->processors as $processor) {
            $processor($record);
        }
    }
}

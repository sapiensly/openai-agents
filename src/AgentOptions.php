<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use InvalidArgumentException;

/**
 * Agent configuration options.
 *
 * This class defines all the available configuration options for an Agent.
 * It provides type safety and IDE autocompletion for a better developer experience.
 */
class AgentOptions
{
    /**
     * Create a new AgentOptions instance.
     *
     * @param string|null $model The OpenAI model to use (e.g., 'gpt-4o')
     * @param float|null $temperature Controls randomness in responses 0.0 is deterministic, 1.0 is most random
     * @param float|null $top_p Controls diversity of responses 0.0 is restrictive, 1.0 is most permissive
     * @param string|null $mode Set to 'autonomous' for autonomous agents (level 4 in progressive enhancement)
     * @param string|null $autonomy_level Level of autonomy ('high', 'medium', 'low'), when `mode` is set to 'autonomous'
     * @param array|null $capabilities Array of capabilities the agent has when `mode` is set to 'autonomous'
     * @param array|null $tools Array of tool names to be auto-registered
     * @param string|null $system_prompt The system prompt to initialize the Agent
     * @param string|null $instructions Optional instructions for the agent (used in responses API)
     * @param int|null $max_turns The maximum number of turns to include as conversation history
     * @param int|null $max_input_tokens The maximum number of input tokens for the agent (default is 4096, max is 8192)
     * @param int|null $max_conversation_tokens The maximum number of tokens to include in the conversation history (default is 10,000, max is 100,000)
     *
     * @throws InvalidArgumentException If any of the provided values are invalid
     */
    public function __construct(
        public string|null $model = null,
        public float|null  $temperature = null,
        public float|null  $top_p = null,
        public string|null $mode = null,
        public string|null $autonomy_level = null,
        public array|null  $capabilities = null,
        public array|null  $tools = null,
        public string|null $system_prompt = null,
        public string|null $instructions = null, // Optional instructions for the agent (responses api)
        public int|null    $max_turns = null,
        public int|null    $max_input_tokens = null,
        public int|null    $max_conversation_tokens = null,
    ) {
        // Establecer valores por defecto dentro del constructor
        $this->model = $this->model ?? config('agents.default.model', 'gpt-4o');
        $this->temperature = $this->temperature ?? config('agents.default.temperature', 0.7);
        $this->top_p = $this->top_p ?? config('agents.default.top_p', 0.9);
        $this->mode = $this->mode ?? config('agents.default.mode', 'default');
        $this->autonomy_level = $this->autonomy_level ?? config('agents.default.autonomy_level', 'low');
        $this->capabilities = $this->capabilities ?? config('agents.default.capabilities', []);
        $this->tools = $this->tools ?? config('agents.default.tools', []);
        $this->system_prompt = $this->system_prompt ?? config('agents.default.system_prompt', '');
        $this->instructions = $this->instructions ?? config('agents.default.instructions', '');
        $this->max_turns = $this->max_turns ?? config('agents.default.max_turns', 10);
        $this->max_input_tokens = $this->max_input_tokens ?? config('agents.default.max_input_tokens', 4096);
        $this->max_conversation_tokens = $this->max_conversation_tokens ?? config('agents.default.max_conversation_tokens', 10000);

        // Validaciones
        if ($this->temperature !== null && ($this->temperature < 0.0 || $this->temperature > 1.0)) {
            throw new InvalidArgumentException('Temperature must be between 0.0 and 1.0');
        }

        if ($this->top_p !== null && ($this->top_p < 0.0 || $this->top_p > 1.0)) {
            throw new InvalidArgumentException('Top_p must be between 0.0 and 1.0');
        }

        if ($this->autonomy_level !== null && !in_array($this->autonomy_level, ['high', 'medium', 'low'])) {
            throw new InvalidArgumentException('Autonomy level must be one of: high, medium, low');
        }

        if ($this->max_turns < 1) {
            throw new InvalidArgumentException('Max turns must be at least 1');
        }

        if ($this->max_input_tokens < 1 || $this->max_input_tokens > 8192) {
            throw new InvalidArgumentException('Max input tokens must be between 1 and 8192');
        }

        if ($this->max_conversation_tokens < 1 || $this->max_conversation_tokens > 100000) {
            throw new InvalidArgumentException('Max conversation tokens must be between 1 and 100000');
        }

    }

    /**
     * Create an AgentOptions instance from an array.
     *
     * @param array $options The options array
     * @return self
     */
    public static function fromArray(array $options): self
    {
        return new self(
            model: $options['model'] ?? config('agents.default.model', 'gpt-4o'),
            temperature: $options['temperature'] ?? config('agents.default.temperature', 0.7),
            top_p: $options['top_p'] ?? config('agents.default.top_p', 0.9),
            mode: $options['mode'] ?? config('agents.default.mode', 'default'),
            autonomy_level: $options['autonomy_level'] ?? config('agents.default.autonomy_level', 'low'),
            capabilities: $options['capabilities'] ?? config('agents.default.capabilities', []),
            tools: $options['tools'] ?? config('agents.default.tools', []),
            system_prompt: $options['system_prompt'] ?? config('agents.default.system_prompt', ''),
            instructions: $options['instructions'] ?? config('agents.default.instructions', ''),
            max_turns: $options['max_turns'] ?? config('agents.default.max_turns', 10),
            max_input_tokens: $options['max_input_tokens'] ?? config('agents.default.max_input_tokens', 4096),
            max_conversation_tokens: $options['max_conversation_tokens'] ?? config('agents.default.max_conversation_tokens', 10000),
        );
    }

    /**
     * Convert the options to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_filter([
            'model' => $this->model,
            'temperature' => $this->temperature,
            'top_p' => $this->top_p,
            'mode' => $this->mode,
            'autonomy_level' => $this->autonomy_level,
            'capabilities' => $this->capabilities,
            'tools' => $this->tools,
            'system_prompt' => $this->system_prompt,
            'instructions' => $this->instructions,
            'max_turns' => $this->max_turns,
            'max_input_tokens' => $this->max_input_tokens,
            'max_conversation_tokens' => $this->max_conversation_tokens,
        ], fn($value) => $value !== null);
    }

    /**
     * Merge with another options instance or array.
     *
     * @param AgentOptions|array $options
     * @return self
     */
    public function merge(self|array $options): self
    {
        $optionsArray = is_array($options) ? $options : $options->toArray();
        $mergedArray = array_merge($this->toArray(), $optionsArray);

        return self::fromArray($mergedArray);
    }

    /**
     * Get an individual option by key.
     *
     * @param string $key The option key
     */
    public function get(string $key): string|array|float|int|null
    {
        return match ($key) {
            'model' => $this->model,
            'temperature' => $this->temperature,
            'top_p' => $this->top_p,
            'mode' => $this->mode,
            'autonomy_level' => $this->autonomy_level,
            'capabilities' => $this->capabilities,
            'tools' => $this->tools,
            'system_prompt' => $this->system_prompt,
            'instructions' => $this->instructions,
            'max_turns' => $this->max_turns,
            'max_input_tokens' => $this->max_input_tokens,
            'max_conversation_tokens' => $this->max_conversation_tokens,
            default => null,
        };
    }

    /**
     * Set Model.
     *
     * @param string $value The value to set
     */
    public function setModel(string $value): self
    {
        $this->model = $value;
        return $this;
    }

    /**
     * Set Temperature.
     *
     * @param float $value The value to set
     */
    public function setTemperature(float $value): self
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException('Temperature must be between 0.0 and 1.0');
        }
        $this->temperature = $value;
        return $this;
    }

    /**
     * Set Top P.
     *
     * @param float $value The value to set
     */
    public function setTopP(float $value): self
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException('Top_p must be between 0.0 and 1.0');
        }
        $this->top_p = $value;
        return $this;
    }

    /**
     * Set Mode.
     *
     * @param string $value The value to set
     */
    public function setMode(string $value): self
    {
        $this->mode = $value;
        return $this;
    }

    /**
     * Set Autonomy Level.
     *
     * @param string $value The value to set
     */
    public function setAutonomyLevel(string $value): self
    {
        if (!in_array($value, ['high', 'medium', 'low'])) {
            throw new InvalidArgumentException('Autonomy level must be one of: high, medium, low');
        }
        $this->autonomy_level = $value;
        return $this;
    }

    /**
     * Set Capabilities.
     *
     * @param array $value The value to set
     */
    public function setCapabilities(array $value): self
    {
        $this->capabilities = $value;
        return $this;
    }

    /**
     * Set Tools.
     *
     * @param array $value The value to set
     */
    public function setTools(array $value): self
    {
        $this->tools = $value;
        return $this;
    }

    /**
     * Set System Prompt.
     *
     * @param string $value The value to set
     */
    public function setSystemPrompt(string $value): self
    {
        $this->system_prompt = $value;
        return $this;
    }

    /**
     * Set Instructions.
     *
     * @param string $value The value to set
     */
    public function setInstructions(string $value): self
    {
        $this->instructions = $value;
        return $this;
    }

    /**
     * Set Max Turns.
     *
     * @param int $value The value to set
     */
    public function setMaxTurns(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Max turns must be at least 1');
        }
        $this->max_turns = $value;
        return $this;
    }

    /**
     * Set Max Input Tokens.
     *
     * @param int $value The value to set
     */
    public function setMaxInputTokens(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Max input tokens must be at least 1');
        }
        if ($value > 8192) {
            throw new InvalidArgumentException('Max input tokens cannot exceed 8192');
        }
        $this->max_input_tokens = $value;
        return $this;
    }

    /**
     * Set Max Conversation Tokens.
     *
     * @param int $value The value to set
     */
    public function setMaxConversationTokens(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Max conversation tokens must be at least 1');
        }
        if ($value > 100000) {
            throw new InvalidArgumentException('Max conversation tokens cannot exceed 100000');
        }
        $this->max_conversation_tokens = $value;
        return $this;
    }

}

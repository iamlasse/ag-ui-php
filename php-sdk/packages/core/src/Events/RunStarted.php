<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a run starts
 *
 * @package AGUI\Core\Events
 */
class RunStarted extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $runId The run identifier
     * @param string|null $agentName Optional name of the agent
     * @param array<string, mixed>|null $input Optional input data for the run
     * @param array<string, mixed>|null $config Optional configuration for the run
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        string $runId,
        public readonly ?string $agentName = null,
        public readonly ?array $input = null,
        public readonly ?array $config = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::RUN_STARTED, $runId, $timestamp, $metadata);
    }

    /**
     * Get the agent name
     *
     * @return string|null
     */
    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    /**
     * Get the input data
     *
     * @return array<string, mixed>|null
     */
    public function getInput(): ?array
    {
        return $this->input;
    }

    /**
     * Get the configuration
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * Get a specific input value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getInputValue(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    /**
     * Get a specific config value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [];

        if ($this->agentName !== null) {
            $data['agentName'] = $this->agentName;
        }

        if ($this->input !== null) {
            $data['input'] = $this->input;
        }

        if ($this->config !== null) {
            $data['config'] = $this->config;
        }

        return $data;
    }

    /**
     * Create a new event with agent name
     *
     * @param string $agentName
     * @return static
     */
    public function withAgentName(string $agentName): static
    {
        return new static(
            $this->id,
            $this->runId,
            $agentName,
            $this->input,
            $this->config,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with input data
     *
     * @param array<string, mixed> $input
     * @return static
     */
    public function withInput(array $input): static
    {
        return new static(
            $this->id,
            $this->runId,
            $this->agentName,
            $input,
            $this->config,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with configuration
     *
     * @param array<string, mixed> $config
     * @return static
     */
    public function withConfig(array $config): static
    {
        return new static(
            $this->id,
            $this->runId,
            $this->agentName,
            $this->input,
            $config,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Validate the event properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        parent::validate();

        $validator = v::key('runId', v::stringType()->notEmpty())
            ->key('agentName', v::optional(v::stringType()->notEmpty()))
            ->key('input', v::optional(v::arrayType()))
            ->key('config', v::optional(v::arrayType()));

        $data = [
            'runId' => $this->runId,
            'agentName' => $this->agentName,
            'input' => $this->input,
            'config' => $this->config
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid RunStarted data');
        }
    }
}
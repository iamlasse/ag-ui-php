<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted for complete state snapshots
 *
 * @package AGUI\Core\Events
 */
class StateSnapshot extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param array<string, mixed> $state The complete state data
     * @param string|null $stateId Optional identifier for this state
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly array $state,
        public readonly ?string $stateId = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::STATE_SNAPSHOT, $runId, $timestamp, $metadata);
    }

    /**
     * Get the state data
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Get the state ID
     *
     * @return string|null
     */
    public function getStateId(): ?string
    {
        return $this->stateId;
    }

    /**
     * Get a specific value from the state
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getStateValue(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * Check if a key exists in the state
     *
     * @param string $key
     * @return bool
     */
    public function hasStateKey(string $key): bool
    {
        return array_key_exists($key, $this->state);
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'state' => $this->state
        ];

        if ($this->stateId !== null) {
            $data['stateId'] = $this->stateId;
        }

        return $data;
    }

    /**
     * Create a new event with updated state
     *
     * @param array<string, mixed> $state
     * @return static
     */
    public function withState(array $state): static
    {
        return new static(
            $this->id,
            $state,
            $this->stateId,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with a different state ID
     *
     * @param string $stateId
     * @return static
     */
    public function withStateId(string $stateId): static
    {
        return new static(
            $this->id,
            $this->state,
            $stateId,
            $this->runId,
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

        $validator = v::key('state', v::arrayType())
            ->key('stateId', v::optional(v::stringType()->notEmpty()));

        $data = [
            'state' => $this->state,
            'stateId' => $this->stateId
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid StateSnapshot data');
        }
    }
}
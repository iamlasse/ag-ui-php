<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Base abstract class for all AG-UI events
 *
 * @package AGUI\Core\Events
 */
abstract class BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param EventType $type The type of event
     * @param string|null $runId Optional run identifier for multiple sequential runs
     * @param int|null $timestamp Optional timestamp when the event occurred
     * @param array<string, mixed>|null $metadata Optional metadata associated with the event
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $id,
        public readonly EventType $type,
        public readonly ?string $runId = null,
        public readonly ?int $timestamp = null,
        public readonly ?array $metadata = null
    ) {
        $this->validate();
    }

    /**
     * Validate the event properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('id', v::stringType()->notEmpty())
            ->key('type', v::instance(EventType::class))
            ->key('runId', v::optional(v::stringType()))
            ->key('timestamp', v::optional(v::intType()->min(0)))
            ->key('metadata', v::optional(v::arrayType()));

        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'runId' => $this->runId,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid event data');
        }
    }

    /**
     * Get the event timestamp (defaults to current time if not set)
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp ?? time();
    }

    /**
     * Get the event type
     *
     * @return EventType
     */
    public function getType(): EventType
    {
        return $this->type;
    }

    /**
     * Get the event ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the run ID
     *
     * @return string|null
     */
    public function getRunId(): ?string
    {
        return $this->runId;
    }

    /**
     * Get the metadata
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     runId?: string|null,
     *     timestamp?: int|null,
     *     metadata?: array<string, mixed>|null
     * }
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type->value
        ];

        if ($this->runId !== null) {
            $data['runId'] = $this->runId;
        }

        if ($this->timestamp !== null) {
            $data['timestamp'] = $this->timestamp;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    /**
     * Convert to JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Create a new event with updated metadata
     *
     * @param array<string, mixed> $metadata
     * @return static
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            $this->id,
            $this->type,
            $this->runId,
            $this->timestamp,
            $metadata
        );
    }

    /**
     * Create a new event with a different run ID
     *
     * @param string $runId
     * @return static
     */
    public function withRunId(string $runId): static
    {
        return new static(
            $this->id,
            $this->type,
            $runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Get event-specific data (to be overridden by subclasses)
     *
     * @return array<string, mixed>
     */
    abstract public function getEventData(): array;

    /**
     * Get the full event data including base properties
     *
     * @return array<string, mixed>
     */
    public function getFullData(): array
    {
        return array_merge($this->toArray(), $this->getEventData());
    }
}
<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted for state deltas using JSON Patch (RFC 6902)
 *
 * @package AGUI\Core\Events
 */
class StateDelta extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param array<int, array<string, mixed>> $patches JSON Patch operations
     * @param string|null $stateId Optional identifier for the target state
     * @param string|null $previousStateId Optional identifier for the previous state
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly array $patches,
        public readonly ?string $stateId = null,
        public readonly ?string $previousStateId = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::STATE_DELTA, $runId, $timestamp, $metadata);
    }

    /**
     * Get the patches
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPatches(): array
    {
        return $this->patches;
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
     * Get the previous state ID
     *
     * @return string|null
     */
    public function getPreviousStateId(): ?string
    {
        return $this->previousStateId;
    }

    /**
     * Get the number of patches
     *
     * @return int
     */
    public function getPatchCount(): int
    {
        return count($this->patches);
    }

    /**
     * Get patches by operation type
     *
     * @param string $operation
     * @return array<int, array<string, mixed>>
     */
    public function getPatchesByOperation(string $operation): array
    {
        return array_filter($this->patches, function ($patch) use ($operation) {
            return ($patch['op'] ?? null) === $operation;
        });
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'patches' => $this->patches
        ];

        if ($this->stateId !== null) {
            $data['stateId'] = $this->stateId;
        }

        if ($this->previousStateId !== null) {
            $data['previousStateId'] = $this->previousStateId;
        }

        return $data;
    }

    /**
     * Create a new event with updated patches
     *
     * @param array<int, array<string, mixed>> $patches
     * @return static
     */
    public function withPatches(array $patches): static
    {
        return new static(
            $this->id,
            $patches,
            $this->stateId,
            $this->previousStateId,
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
            $this->patches,
            $stateId,
            $this->previousStateId,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with a different previous state ID
     *
     * @param string $previousStateId
     * @return static
     */
    public function withPreviousStateId(string $previousStateId): static
    {
        return new static(
            $this->id,
            $this->patches,
            $this->stateId,
            $previousStateId,
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

        // Check for empty patches
        if (empty($this->patches)) {
            throw new ValidationException('Patches cannot be empty');
        }

        $validator = v::key('patches', v::arrayType()->each(v::arrayType()
            ->key('op', v::stringType()->in(['add', 'remove', 'replace', 'move', 'copy', 'test']))
            ->key('path', v::stringType()->notEmpty())
        ))
            ->key('stateId', v::optional(v::stringType()->notEmpty()))
            ->key('previousStateId', v::optional(v::stringType()->notEmpty()));

        $data = [
            'patches' => $this->patches,
            'stateId' => $this->stateId,
            'previousStateId' => $this->previousStateId
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid StateDelta data');
        }
    }
}
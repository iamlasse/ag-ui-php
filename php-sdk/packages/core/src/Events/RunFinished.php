<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a run finishes
 *
 * @package AGUI\Core\Events
 */
class RunFinished extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $runId The run identifier
     * @param bool $success Whether the run completed successfully
     * @param string|null $result Optional result data
     * @param string|null $error Optional error message if the run failed
     * @param int|null $duration Optional duration in milliseconds
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        string $runId,
        public readonly bool $success,
        public readonly ?string $result = null,
        public readonly ?string $error = null,
        public readonly ?int $duration = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::RUN_FINISHED, $runId, $timestamp, $metadata);
    }

    /**
     * Check if the run was successful
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the result data
     *
     * @return string|null
     */
    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * Get the error message
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the duration
     *
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'success' => $this->success
        ];

        if ($this->result !== null) {
            $data['result'] = $this->result;
        }

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        if ($this->duration !== null) {
            $data['duration'] = $this->duration;
        }

        return $data;
    }

    /**
     * Create a new event with success status
     *
     * @param bool $success
     * @return static
     */
    public function withSuccess(bool $success): static
    {
        return new static(
            $this->id,
            $this->runId,
            $success,
            $this->result,
            $this->error,
            $this->duration,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with result data
     *
     * @param string $result
     * @return static
     */
    public function withResult(string $result): static
    {
        return new static(
            $this->id,
            $this->runId,
            $this->success,
            $result,
            $this->error,
            $this->duration,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with error message
     *
     * @param string $error
     * @return static
     */
    public function withError(string $error): static
    {
        return new static(
            $this->id,
            $this->runId,
            $this->success,
            $this->result,
            $error,
            $this->duration,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with duration
     *
     * @param int $duration
     * @return static
     */
    public function withDuration(int $duration): static
    {
        return new static(
            $this->id,
            $this->runId,
            $this->success,
            $this->result,
            $this->error,
            $duration,
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
            ->key('success', v::boolType())
            ->key('result', v::optional(v::stringType()))
            ->key('error', v::optional(v::stringType()))
            ->key('duration', v::optional(v::intType()->min(0)));

        $data = [
            'runId' => $this->runId,
            'success' => $this->success,
            'result' => $this->result,
            'error' => $this->error,
            'duration' => $this->duration
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid RunFinished data');
        }
    }
}
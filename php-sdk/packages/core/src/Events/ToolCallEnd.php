<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a tool call ends
 *
 * @package AGUI\Core\Events
 */
class ToolCallEnd extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $toolCallId The ID of the tool call that ended
     * @param string|null $finalResult Optional final result if different from chunks
     * @param int|null $totalChunks Optional total number of chunks
     * @param bool|null $success Optional flag indicating if the tool call was successful
     * @param string|null $error Optional error message if the tool call failed
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly string $toolCallId,
        public readonly ?string $finalResult = null,
        public readonly ?int $totalChunks = null,
        public readonly ?bool $success = null,
        public readonly ?string $error = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TOOL_CALL_END, $runId, $timestamp, $metadata);
    }

    /**
     * Get the tool call ID
     *
     * @return string
     */
    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    /**
     * Get the final result
     *
     * @return string|null
     */
    public function getFinalResult(): ?string
    {
        return $this->finalResult;
    }

    /**
     * Get the total number of chunks
     *
     * @return int|null
     */
    public function getTotalChunks(): ?int
    {
        return $this->totalChunks;
    }

    /**
     * Check if the tool call was successful
     *
     * @return bool|null
     */
    public function isSuccess(): ?bool
    {
        return $this->success;
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
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'toolCallId' => $this->toolCallId
        ];

        if ($this->finalResult !== null) {
            $data['finalResult'] = $this->finalResult;
        }

        if ($this->totalChunks !== null) {
            $data['totalChunks'] = $this->totalChunks;
        }

        if ($this->success !== null) {
            $data['success'] = $this->success;
        }

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }

    /**
     * Create a new event with final result
     *
     * @param string $finalResult
     * @return static
     */
    public function withFinalResult(string $finalResult): static
    {
        return new static(
            $this->id,
            $this->toolCallId,
            $finalResult,
            $this->totalChunks,
            $this->success,
            $this->error,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
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
            $this->toolCallId,
            $this->finalResult,
            $this->totalChunks,
            $success,
            $this->error,
            $this->runId,
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
            $this->toolCallId,
            $this->finalResult,
            $this->totalChunks,
            $this->success,
            $error,
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

        $validator = v::key('toolCallId', v::stringType()->notEmpty())
            ->key('finalResult', v::optional(v::stringType()))
            ->key('totalChunks', v::optional(v::intType()->min(0)))
            ->key('success', v::optional(v::boolType()))
            ->key('error', v::optional(v::stringType()));

        $data = [
            'toolCallId' => $this->toolCallId,
            'finalResult' => $this->finalResult,
            'totalChunks' => $this->totalChunks,
            'success' => $this->success,
            'error' => $this->error
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid ToolCallEnd data');
        }
    }
}
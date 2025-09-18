<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted for each chunk of a tool call result
 *
 * @package AGUI\Core\Events
 */
class ToolCallChunk extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $toolCallId The ID of the tool call this chunk belongs to
     * @param string|null $content The chunk content (can be null for streaming)
     * @param int|null $chunkIndex Optional index of this chunk
     * @param bool|null $isLast Optional flag indicating if this is the last chunk
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly string $toolCallId,
        public readonly ?string $content = null,
        public readonly ?int $chunkIndex = null,
        public readonly ?bool $isLast = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TOOL_CALL_CHUNK, $runId, $timestamp, $metadata);
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
     * Get the chunk content
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the chunk index
     *
     * @return int|null
     */
    public function getChunkIndex(): ?int
    {
        return $this->chunkIndex;
    }

    /**
     * Check if this is the last chunk
     *
     * @return bool|null
     */
    public function isLast(): ?bool
    {
        return $this->isLast;
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

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->chunkIndex !== null) {
            $data['chunkIndex'] = $this->chunkIndex;
        }

        if ($this->isLast !== null) {
            $data['isLast'] = $this->isLast;
        }

        return $data;
    }

    /**
     * Create a new event with updated content
     *
     * @param string|null $content
     * @return static
     */
    public function withContent(?string $content): static
    {
        return new static(
            $this->id,
            $this->toolCallId,
            $content,
            $this->chunkIndex,
            $this->isLast,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with a different chunk index
     *
     * @param int $chunkIndex
     * @return static
     */
    public function withChunkIndex(int $chunkIndex): static
    {
        return new static(
            $this->id,
            $this->toolCallId,
            $this->content,
            $chunkIndex,
            $this->isLast,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with isLast flag
     *
     * @param bool $isLast
     * @return static
     */
    public function withIsLast(bool $isLast): static
    {
        return new static(
            $this->id,
            $this->toolCallId,
            $this->content,
            $this->chunkIndex,
            $isLast,
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
            ->key('content', v::optional(v::stringType()))
            ->key('chunkIndex', v::optional(v::intType()->min(0)))
            ->key('isLast', v::optional(v::boolType()));

        $data = [
            'toolCallId' => $this->toolCallId,
            'content' => $this->content,
            'chunkIndex' => $this->chunkIndex,
            'isLast' => $this->isLast
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid ToolCallChunk data');
        }
    }
}
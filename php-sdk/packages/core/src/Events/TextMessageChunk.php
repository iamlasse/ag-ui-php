<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted for each chunk of a text message
 *
 * @package AGUI\Core\Events
 */
class TextMessageChunk extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $messageId The ID of the message this chunk belongs to
     * @param string $content The chunk content
     * @param int|null $chunkIndex Optional index of this chunk
     * @param bool|null $isLast Optional flag indicating if this is the last chunk
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly string $messageId,
        public readonly string $content,
        public readonly ?int $chunkIndex = null,
        public readonly ?bool $isLast = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TEXT_MESSAGE_CHUNK, $runId, $timestamp, $metadata);
    }

    /**
     * Get the message ID
     *
     * @return string
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * Get the chunk content
     *
     * @return string
     */
    public function getContent(): string
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
            'messageId' => $this->messageId,
            'content' => $this->content
        ];

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
     * @param string $content
     * @return static
     */
    public function withContent(string $content): static
    {
        return new static(
            $this->id,
            $this->messageId,
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
            $this->messageId,
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
            $this->messageId,
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

        $validator = v::key('messageId', v::stringType()->notEmpty())
            ->key('content', v::stringType())
            ->key('chunkIndex', v::optional(v::intType()->min(0)))
            ->key('isLast', v::optional(v::boolType()));

        $data = [
            'messageId' => $this->messageId,
            'content' => $this->content,
            'chunkIndex' => $this->chunkIndex,
            'isLast' => $this->isLast
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid TextMessageChunk data');
        }
    }
}
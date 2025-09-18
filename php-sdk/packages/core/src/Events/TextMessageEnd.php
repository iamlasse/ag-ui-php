<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a text message ends
 *
 * @package AGUI\Core\Events
 */
class TextMessageEnd extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param string $messageId The ID of the message that ended
     * @param string|null $finalContent Optional final content if different from chunks
     * @param int|null $totalChunks Optional total number of chunks
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly string $messageId,
        public readonly ?string $finalContent = null,
        public readonly ?int $totalChunks = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TEXT_MESSAGE_END, $runId, $timestamp, $metadata);
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
     * Get the final content
     *
     * @return string|null
     */
    public function getFinalContent(): ?string
    {
        return $this->finalContent;
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
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'messageId' => $this->messageId
        ];

        if ($this->finalContent !== null) {
            $data['finalContent'] = $this->finalContent;
        }

        if ($this->totalChunks !== null) {
            $data['totalChunks'] = $this->totalChunks;
        }

        return $data;
    }

    /**
     * Create a new event with final content
     *
     * @param string $finalContent
     * @return static
     */
    public function withFinalContent(string $finalContent): static
    {
        return new static(
            $this->id,
            $this->messageId,
            $finalContent,
            $this->totalChunks,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with total chunks
     *
     * @param int $totalChunks
     * @return static
     */
    public function withTotalChunks(int $totalChunks): static
    {
        return new static(
            $this->id,
            $this->messageId,
            $this->finalContent,
            $totalChunks,
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
            ->key('finalContent', v::optional(v::stringType()))
            ->key('totalChunks', v::optional(v::intType()->min(0)));

        $data = [
            'messageId' => $this->messageId,
            'finalContent' => $this->finalContent,
            'totalChunks' => $this->totalChunks
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid TextMessageEnd data');
        }
    }
}
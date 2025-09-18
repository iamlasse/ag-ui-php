<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Types\BaseMessage;
use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted for conversation history snapshots
 *
 * @package AGUI\Core\Events
 */
class MessagesSnapshot extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param array<int, BaseMessage> $messages The conversation history
     * @param string|null $snapshotId Optional identifier for this snapshot
     * @param int|null $totalMessages Optional total count of messages
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly array $messages,
        public readonly ?string $snapshotId = null,
        public readonly ?int $totalMessages = null,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::MESSAGES_SNAPSHOT, $runId, $timestamp, $metadata);
    }

    /**
     * Get the messages
     *
     * @return array<int, BaseMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the snapshot ID
     *
     * @return string|null
     */
    public function getSnapshotId(): ?string
    {
        return $this->snapshotId;
    }

    /**
     * Get the total number of messages
     *
     * @return int
     */
    public function getTotalMessages(): int
    {
        return $this->totalMessages ?? count($this->messages);
    }

    /**
     * Get a message by index
     *
     * @param int $index
     * @return BaseMessage|null
     */
    public function getMessage(int $index): ?BaseMessage
    {
        return $this->messages[$index] ?? null;
    }

    /**
     * Get messages by role
     *
     * @param string $role
     * @return array<int, BaseMessage>
     */
    public function getMessagesByRole(string $role): array
    {
        return array_filter($this->messages, function ($message) use ($role) {
            return $message->getRole() === $role;
        });
    }

    /**
     * Get the last message
     *
     * @return BaseMessage|null
     */
    public function getLastMessage(): ?BaseMessage
    {
        $count = count($this->messages);
        return $count > 0 ? $this->messages[$count - 1] : null;
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        $data = [
            'messages' => array_map(function ($message) {
                return $message->toArray();
            }, $this->messages)
        ];

        if ($this->snapshotId !== null) {
            $data['snapshotId'] = $this->snapshotId;
        }

        if ($this->totalMessages !== null) {
            $data['totalMessages'] = $this->totalMessages;
        }

        return $data;
    }

    /**
     * Create a new event with updated messages
     *
     * @param array<int, BaseMessage> $messages
     * @return static
     */
    public function withMessages(array $messages): static
    {
        return new static(
            $this->id,
            $messages,
            $this->snapshotId,
            $this->totalMessages,
            $this->runId,
            $this->timestamp,
            $this->metadata
        );
    }

    /**
     * Create a new event with a different snapshot ID
     *
     * @param string $snapshotId
     * @return static
     */
    public function withSnapshotId(string $snapshotId): static
    {
        return new static(
            $this->id,
            $this->messages,
            $snapshotId,
            $this->totalMessages,
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

        $validator = v::key('messages', v::arrayType()->each(v::instance(BaseMessage::class)))
            ->key('snapshotId', v::optional(v::stringType()->notEmpty()))
            ->key('totalMessages', v::optional(v::intType()->min(0)));

        $data = [
            'messages' => $this->messages,
            'snapshotId' => $this->snapshotId,
            'totalMessages' => $this->totalMessages
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid MessagesSnapshot data');
        }
    }
}
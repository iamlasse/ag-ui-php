<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Types\BaseMessage;
use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a text message starts
 *
 * @package AGUI\Core\Events
 */
class TextMessageStart extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param BaseMessage $message The message being started
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly BaseMessage $message,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TEXT_MESSAGE_START, $runId, $timestamp, $metadata);
    }

    /**
     * Get the message
     *
     * @return BaseMessage
     */
    public function getMessage(): BaseMessage
    {
        return $this->message;
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        return [
            'message' => $this->message->toArray()
        ];
    }

    /**
     * Create a new event with a different message
     *
     * @param BaseMessage $message
     * @return static
     */
    public function withMessage(BaseMessage $message): static
    {
        return new static(
            $this->id,
            $message,
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

        $validator = v::key('message', v::instance(BaseMessage::class));

        $data = [
            'message' => $this->message
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid TextMessageStart data');
        }
    }
}
<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Types\ToolCall;
use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Event emitted when a tool call starts
 *
 * @package AGUI\Core\Events
 */
class ToolCallStart extends BaseEvent
{
    /**
     * @param string $id Unique identifier for the event
     * @param ToolCall $toolCall The tool call being started
     * @param string|null $runId Optional run identifier
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @throws ValidationException
     */
    public function __construct(
        string $id,
        public readonly ToolCall $toolCall,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ) {
        parent::__construct($id, EventType::TOOL_CALL_START, $runId, $timestamp, $metadata);
    }

    /**
     * Get the tool call
     *
     * @return ToolCall
     */
    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    /**
     * Get event-specific data
     *
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        return [
            'toolCall' => $this->toolCall->toArray()
        ];
    }

    /**
     * Create a new event with a different tool call
     *
     * @param ToolCall $toolCall
     * @return static
     */
    public function withToolCall(ToolCall $toolCall): static
    {
        return new static(
            $this->id,
            $toolCall,
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

        $validator = v::key('toolCall', v::instance(ToolCall::class));

        $data = [
            'toolCall' => $this->toolCall
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid ToolCallStart data');
        }
    }
}
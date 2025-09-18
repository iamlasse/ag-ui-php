<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

/**
 * Enumeration of all possible event types in the AG-UI protocol
 *
 * @package AGUI\Core\Events
 */
enum EventType: string
{
    // Lifecycle events
    case RUN_STARTED = 'run_started';
    case RUN_FINISHED = 'run_finished';

    // Text message events
    case TEXT_MESSAGE_START = 'text_message_start';
    case TEXT_MESSAGE_CHUNK = 'text_message_chunk';
    case TEXT_MESSAGE_END = 'text_message_end';

    // Tool call events
    case TOOL_CALL_START = 'tool_call_start';
    case TOOL_CALL_CHUNK = 'tool_call_chunk';
    case TOOL_CALL_END = 'tool_call_end';

    // State management events
    case STATE_SNAPSHOT = 'state_snapshot';
    case STATE_DELTA = 'state_delta';
    case MESSAGES_SNAPSHOT = 'messages_snapshot';

    /**
     * Get all valid event types
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if an event type is valid
     *
     * @param string $eventType
     * @return bool
     */
    public static function isValid(string $eventType): bool
    {
        return in_array($eventType, self::all(), true);
    }

    /**
     * Check if this is a lifecycle event
     *
     * @return bool
     */
    public function isLifecycleEvent(): bool
    {
        return in_array($this, [self::RUN_STARTED, self::RUN_FINISHED], true);
    }

    /**
     * Check if this is a text message event
     *
     * @return bool
     */
    public function isTextMessageEvent(): bool
    {
        return in_array($this, [self::TEXT_MESSAGE_START, self::TEXT_MESSAGE_CHUNK, self::TEXT_MESSAGE_END], true);
    }

    /**
     * Check if this is a tool call event
     *
     * @return bool
     */
    public function isToolCallEvent(): bool
    {
        return in_array($this, [self::TOOL_CALL_START, self::TOOL_CALL_CHUNK, self::TOOL_CALL_END], true);
    }

    /**
     * Check if this is a state management event
     *
     * @return bool
     */
    public function isStateEvent(): bool
    {
        return in_array($this, [self::STATE_SNAPSHOT, self::STATE_DELTA, self::MESSAGES_SNAPSHOT], true);
    }

    /**
     * Get the event category
     *
     * @return string
     */
    public function getCategory(): string
    {
        return match($this) {
            self::RUN_STARTED, self::RUN_FINISHED => 'lifecycle',
            self::TEXT_MESSAGE_START, self::TEXT_MESSAGE_CHUNK, self::TEXT_MESSAGE_END => 'text_message',
            self::TOOL_CALL_START, self::TOOL_CALL_CHUNK, self::TOOL_CALL_END => 'tool_call',
            self::STATE_SNAPSHOT, self::STATE_DELTA, self::MESSAGES_SNAPSHOT => 'state',
        };
    }
}
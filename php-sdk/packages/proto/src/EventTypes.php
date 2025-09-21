<?php

declare(strict_types=1);

namespace AGUI\Proto;

/**
 * AG-UI Event Type Constants
 * 
 * This class provides constants for all supported event types,
 * maintaining consistency with the TypeScript implementation.
 */
class EventTypes
{
    public const TEXT_MESSAGE_START = 'TEXT_MESSAGE_START';
    public const TEXT_MESSAGE_CONTENT = 'TEXT_MESSAGE_CONTENT';
    public const TEXT_MESSAGE_END = 'TEXT_MESSAGE_END';
    public const TOOL_CALL_START = 'TOOL_CALL_START';
    public const TOOL_CALL_ARGS = 'TOOL_CALL_ARGS';
    public const TOOL_CALL_END = 'TOOL_CALL_END';
    public const STATE_SNAPSHOT = 'STATE_SNAPSHOT';
    public const STATE_DELTA = 'STATE_DELTA';
    public const MESSAGES_SNAPSHOT = 'MESSAGES_SNAPSHOT';
    public const RAW = 'RAW';
    public const CUSTOM = 'CUSTOM';
    public const RUN_STARTED = 'RUN_STARTED';
    public const RUN_FINISHED = 'RUN_FINISHED';
    public const RUN_ERROR = 'RUN_ERROR';
    public const STEP_STARTED = 'STEP_STARTED';
    public const STEP_FINISHED = 'STEP_FINISHED';

    /**
     * Get all event types as an array.
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }

    /**
     * Check if an event type is valid.
     */
    public static function isValid(string $eventType): bool
    {
        return in_array($eventType, self::all(), true);
    }
}

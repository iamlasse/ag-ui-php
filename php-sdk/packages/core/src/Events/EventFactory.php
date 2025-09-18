<?php

declare(strict_types=1);

namespace AGUI\Core\Events;

use AGUI\Core\Types\BaseMessage;
use AGUI\Core\Types\ToolCall;
use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Factory for creating type-safe event instances
 *
 * @package AGUI\Core\Events
 */
final class EventFactory
{
    /**
     * Create a RunStarted event
     *
     * @param string $runId The run identifier
     * @param string|null $agentName Optional name of the agent
     * @param array<string, mixed>|null $input Optional input data for the run
     * @param array<string, mixed>|null $config Optional configuration for the run
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return RunStarted
     * @throws ValidationException
     */
    public static function createRunStarted(
        string $runId,
        ?string $agentName = null,
        ?array $input = null,
        ?array $config = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): RunStarted {
        return new RunStarted(
            $id ?? self::generateEventId(),
            $runId,
            $agentName,
            $input,
            $config,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a RunFinished event
     *
     * @param string $runId The run identifier
     * @param bool $success Whether the run completed successfully
     * @param string|null $result Optional result data
     * @param string|null $error Optional error message if the run failed
     * @param int|null $duration Optional duration in milliseconds
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return RunFinished
     * @throws ValidationException
     */
    public static function createRunFinished(
        string $runId,
        bool $success,
        ?string $result = null,
        ?string $error = null,
        ?int $duration = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): RunFinished {
        return new RunFinished(
            $id ?? self::generateEventId(),
            $runId,
            $success,
            $result,
            $error,
            $duration,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a TextMessageStart event
     *
     * @param BaseMessage $message The message being started
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return TextMessageStart
     * @throws ValidationException
     */
    public static function createTextMessageStart(
        BaseMessage $message,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): TextMessageStart {
        return new TextMessageStart(
            $id ?? self::generateEventId(),
            $message,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a TextMessageChunk event
     *
     * @param string $messageId The ID of the message this chunk belongs to
     * @param string $content The chunk content
     * @param int|null $chunkIndex Optional index of this chunk
     * @param bool|null $isLast Optional flag indicating if this is the last chunk
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return TextMessageChunk
     * @throws ValidationException
     */
    public static function createTextMessageChunk(
        string $messageId,
        string $content,
        ?int $chunkIndex = null,
        ?bool $isLast = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): TextMessageChunk {
        return new TextMessageChunk(
            $id ?? self::generateEventId(),
            $messageId,
            $content,
            $chunkIndex,
            $isLast,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a TextMessageEnd event
     *
     * @param string $messageId The ID of the message that ended
     * @param string|null $finalContent Optional final content if different from chunks
     * @param int|null $totalChunks Optional total number of chunks
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return TextMessageEnd
     * @throws ValidationException
     */
    public static function createTextMessageEnd(
        string $messageId,
        ?string $finalContent = null,
        ?int $totalChunks = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): TextMessageEnd {
        return new TextMessageEnd(
            $id ?? self::generateEventId(),
            $messageId,
            $finalContent,
            $totalChunks,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a ToolCallStart event
     *
     * @param ToolCall $toolCall The tool call being started
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return ToolCallStart
     * @throws ValidationException
     */
    public static function createToolCallStart(
        ToolCall $toolCall,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): ToolCallStart {
        return new ToolCallStart(
            $id ?? self::generateEventId(),
            $toolCall,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a ToolCallChunk event
     *
     * @param string $toolCallId The ID of the tool call this chunk belongs to
     * @param string|null $content The chunk content (can be null for streaming)
     * @param int|null $chunkIndex Optional index of this chunk
     * @param bool|null $isLast Optional flag indicating if this is the last chunk
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return ToolCallChunk
     * @throws ValidationException
     */
    public static function createToolCallChunk(
        string $toolCallId,
        ?string $content = null,
        ?int $chunkIndex = null,
        ?bool $isLast = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): ToolCallChunk {
        return new ToolCallChunk(
            $id ?? self::generateEventId(),
            $toolCallId,
            $content,
            $chunkIndex,
            $isLast,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a ToolCallEnd event
     *
     * @param string $toolCallId The ID of the tool call that ended
     * @param string|null $finalResult Optional final result if different from chunks
     * @param int|null $totalChunks Optional total number of chunks
     * @param bool|null $success Optional flag indicating if the tool call was successful
     * @param string|null $error Optional error message if the tool call failed
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return ToolCallEnd
     * @throws ValidationException
     */
    public static function createToolCallEnd(
        string $toolCallId,
        ?string $finalResult = null,
        ?int $totalChunks = null,
        ?bool $success = null,
        ?string $error = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): ToolCallEnd {
        return new ToolCallEnd(
            $id ?? self::generateEventId(),
            $toolCallId,
            $finalResult,
            $totalChunks,
            $success,
            $error,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a StateSnapshot event
     *
     * @param array<string, mixed> $state The complete state data
     * @param string|null $stateId Optional identifier for this state
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return StateSnapshot
     * @throws ValidationException
     */
    public static function createStateSnapshot(
        array $state,
        ?string $stateId = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): StateSnapshot {
        return new StateSnapshot(
            $id ?? self::generateEventId(),
            $state,
            $stateId,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a StateDelta event
     *
     * @param array<int, array<string, mixed>> $patches JSON Patch operations
     * @param string|null $stateId Optional identifier for the target state
     * @param string|null $previousStateId Optional identifier for the previous state
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return StateDelta
     * @throws ValidationException
     */
    public static function createStateDelta(
        array $patches,
        ?string $stateId = null,
        ?string $previousStateId = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): StateDelta {
        return new StateDelta(
            $id ?? self::generateEventId(),
            $patches,
            $stateId,
            $previousStateId,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create a MessagesSnapshot event
     *
     * @param array<int, BaseMessage> $messages The conversation history
     * @param string|null $snapshotId Optional identifier for this snapshot
     * @param int|null $totalMessages Optional total count of messages
     * @param string|null $runId Optional run identifier
     * @param string|null $id Optional event ID (auto-generated if not provided)
     * @param int|null $timestamp Optional timestamp
     * @param array<string, mixed>|null $metadata Optional metadata
     * @return MessagesSnapshot
     * @throws ValidationException
     */
    public static function createMessagesSnapshot(
        array $messages,
        ?string $snapshotId = null,
        ?int $totalMessages = null,
        ?string $runId = null,
        ?string $id = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): MessagesSnapshot {
        return new MessagesSnapshot(
            $id ?? self::generateEventId(),
            $messages,
            $snapshotId,
            $totalMessages,
            $runId,
            $timestamp,
            $metadata
        );
    }

    /**
     * Create an event from an array representation
     *
     * @param array<string, mixed> $data
     * @return BaseEvent
     * @throws ValidationException
     */
    public static function fromArray(array $data): BaseEvent
    {
        $validator = v::key('type', v::stringType()->notEmpty())
            ->key('id', v::stringType()->notEmpty());

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid event data for factory creation');
        }

        $eventType = EventType::tryFrom($data['type']);
        if ($eventType === null) {
            throw new ValidationException('Invalid event type: ' . $data['type']);
        }

        return match($eventType) {
            EventType::RUN_STARTED => self::createRunStarted(
                $data['runId'] ?? throw new ValidationException('runId is required for RUN_STARTED'),
                $data['agentName'] ?? null,
                $data['input'] ?? null,
                $data['config'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::RUN_FINISHED => self::createRunFinished(
                $data['runId'] ?? throw new ValidationException('runId is required for RUN_FINISHED'),
                $data['success'] ?? throw new ValidationException('success is required for RUN_FINISHED'),
                $data['result'] ?? null,
                $data['error'] ?? null,
                $data['duration'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TEXT_MESSAGE_START => self::createTextMessageStart(
                \AGUI\Core\Types\UserMessage::fromArray($data['message'] ?? throw new ValidationException('message is required for TEXT_MESSAGE_START')),
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TEXT_MESSAGE_CHUNK => self::createTextMessageChunk(
                $data['messageId'] ?? throw new ValidationException('messageId is required for TEXT_MESSAGE_CHUNK'),
                $data['content'] ?? throw new ValidationException('content is required for TEXT_MESSAGE_CHUNK'),
                $data['chunkIndex'] ?? null,
                $data['isLast'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TEXT_MESSAGE_END => self::createTextMessageEnd(
                $data['messageId'] ?? throw new ValidationException('messageId is required for TEXT_MESSAGE_END'),
                $data['finalContent'] ?? null,
                $data['totalChunks'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TOOL_CALL_START => self::createToolCallStart(
                ToolCall::fromArray($data['toolCall'] ?? throw new ValidationException('toolCall is required for TOOL_CALL_START')),
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TOOL_CALL_CHUNK => self::createToolCallChunk(
                $data['toolCallId'] ?? throw new ValidationException('toolCallId is required for TOOL_CALL_CHUNK'),
                $data['content'] ?? null,
                $data['chunkIndex'] ?? null,
                $data['isLast'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::TOOL_CALL_END => self::createToolCallEnd(
                $data['toolCallId'] ?? throw new ValidationException('toolCallId is required for TOOL_CALL_END'),
                $data['finalResult'] ?? null,
                $data['totalChunks'] ?? null,
                $data['success'] ?? null,
                $data['error'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::STATE_SNAPSHOT => self::createStateSnapshot(
                $data['state'] ?? throw new ValidationException('state is required for STATE_SNAPSHOT'),
                $data['stateId'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::STATE_DELTA => self::createStateDelta(
                $data['patches'] ?? throw new ValidationException('patches is required for STATE_DELTA'),
                $data['stateId'] ?? null,
                $data['previousStateId'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
            EventType::MESSAGES_SNAPSHOT => self::createMessagesSnapshot(
                array_map(fn($msg) => \AGUI\Core\Types\UserMessage::fromArray($msg), $data['messages'] ?? throw new ValidationException('messages is required for MESSAGES_SNAPSHOT')),
                $data['snapshotId'] ?? null,
                $data['totalMessages'] ?? null,
                $data['runId'] ?? null,
                $data['id'] ?? null,
                $data['timestamp'] ?? null,
                $data['metadata'] ?? null
            ),
        };
    }

    /**
     * Generate a unique event ID
     *
     * @return string
     */
    private static function generateEventId(): string
    {
        return 'event_' . uniqid('', true) . '_' . bin2hex(random_bytes(4));
    }
}
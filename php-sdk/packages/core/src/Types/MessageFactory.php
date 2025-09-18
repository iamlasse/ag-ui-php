<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;

/**
 * Factory class for creating message instances from arrays
 *
 * @package AGUI\Core\Types
 */
final class MessageFactory
{
    /**
     * Create a message instance from an array
     *
     * @param array{
     *     id: string,
     *     role: string,
     *     content?: string|null,
     *     name?: string|null,
     *     toolCalls?: array<int, array{ id: string, type: string, function: array{ name: string, arguments: string } }>,
     *     toolCallId?: string,
     *     error?: string|null
     * } $data
     * @return BaseMessage|ToolMessage
     * @throws ValidationException
     */
    public static function fromArray(array $data): BaseMessage|ToolMessage
    {
        $role = $data['role'] ?? null;

        return match ($role) {
            'developer' => DeveloperMessage::fromArray($data),
            'system' => SystemMessage::fromArray($data),
            'assistant' => AssistantMessage::fromArray($data),
            'user' => UserMessage::fromArray($data),
            'tool' => ToolMessage::fromArray($data),
            default => throw new ValidationException("Unknown message role: {$role}")
        };
    }

    /**
     * Create messages from an array of message arrays
     *
     * @param array<int, array> $messagesData
     * @return array<int, BaseMessage|ToolMessage>
     * @throws ValidationException
     */
    public static function fromArrayMultiple(array $messagesData): array
    {
        return array_map(
            fn($messageData) => self::fromArray($messageData),
            $messagesData
        );
    }
}

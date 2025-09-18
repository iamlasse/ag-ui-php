<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

/**
 * Message from a user
 *
 * @package AGUI\Core\Types
 */
final class UserMessage extends BaseMessage
{
    /**
     * @param string $id Unique identifier for the message
     * @param string $content The content of the message
     * @param string|null $name Optional name for the message sender
     */
    public function __construct(
        string $id,
        string $content,
        ?string $name = null
    ) {
        parent::__construct($id, 'user', $content, $name);
    }

    /**
     * Create a UserMessage from an array
     *
     * @param array{ id: string, content: string, name?: string|null } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw new \InvalidArgumentException('Missing or invalid "id" key in data array');
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            throw new \InvalidArgumentException('Missing or invalid "content" key in data array');
        }

        return new static(
            $data['id'],
            $data['content'],
            $data['name'] ?? null
        );
    }
}

<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

/**
 * Message from a developer
 *
 * @package AGUI\Core\Types
 */
final class DeveloperMessage extends BaseMessage
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
        parent::__construct($id, 'developer', $content, $name);
    }

    /**
     * Create a DeveloperMessage from an array
     *
     * @param array{ id: string, content: string, name?: string|null } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('Missing required key: id');
        }

        if (!isset($data['content'])) {
            throw new \InvalidArgumentException('Missing required key: content');
        }

        return new static(
            $data['id'],
            $data['content'],
            $data['name'] ?? null
        );
    }
}

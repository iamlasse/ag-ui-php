<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

/**
 * System message
 *
 * @package AGUI\Core\Types
 */
final class SystemMessage extends BaseMessage
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
        parent::__construct($id, 'system', $content, $name);
    }

    /**
     * Create a SystemMessage from an array
     *
     * @param array{ id: string, content: string, name?: string|null } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['id'],
            $data['content'],
            $data['name'] ?? null
        );
    }
}

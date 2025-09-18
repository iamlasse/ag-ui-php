<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Base class for all message types
 *
 * @package AGUI\Core\Types
 */
abstract class BaseMessage
{
    /**
     * @param string $id Unique identifier for the message
     * @param string $role The role of the message sender
     * @param string|null $content The content of the message
     * @param string|null $name Optional name for the message sender
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $id,
        public readonly string $role,
        public readonly ?string $content = null,
        public readonly ?string $name = null
    ) {
        $this->validate();
    }

    /**
     * Validate the message properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('id', v::stringType()->notEmpty())
            ->key('role', v::stringType()->notEmpty())
            ->key('content', v::optional(v::stringType()))
            ->key('name', v::optional(v::stringType()));

        $data = [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'name' => $this->name
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid message data');
        }
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     id: string,
     *     role: string,
     *     content?: string|null,
     *     name?: string|null
     * }
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'role' => $this->role
        ];

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }

    /**
     * Convert to JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get the message content
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the message role
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the message ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Create a BaseMessage from an array
     *
     * @param array{ id: string, role: string, content?: string|null, name?: string|null } $data
     * @return static
     * @throws ValidationException
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['id'],
            $data['role'],
            $data['content'] ?? null,
            $data['name'] ?? null
        );
    }
}

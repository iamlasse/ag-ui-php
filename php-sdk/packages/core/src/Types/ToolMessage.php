<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Message containing the result of a tool call
 *
 * @package AGUI\Core\Types
 */
final class ToolMessage
{
    /**
     * @param string $id Unique identifier for the message
     * @param string $content The content/result of the tool call
     * @param string $toolCallId The ID of the tool call this message responds to
     * @param string|null $error Optional error message if the tool call failed
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly string $toolCallId,
        public readonly ?string $error = null
    ) {
        $this->validate();
    }

    /**
     * Validate the tool message properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('id', v::stringType()->notEmpty())
            ->key('content', v::stringType()->notEmpty())
            ->key('toolCallId', v::stringType()->notEmpty())
            ->key('error', v::optional(v::stringType()));

        $data = [
            'id' => $this->id,
            'content' => $this->content,
            'toolCallId' => $this->toolCallId,
            'error' => $this->error
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid tool message data');
        }
    }

    /**
     * Create a ToolMessage from an array
     *
     * @param array{
     *     id: string,
     *     content: string,
     *     toolCallId: string,
     *     error?: string|null
     * } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['id'],
            $data['content'],
            $data['toolCallId'],
            $data['error'] ?? null
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     id: string,
     *     content: string,
     *     role: string,
     *     toolCallId: string,
     *     error?: string|null
     * }
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'content' => $this->content,
            'role' => 'tool',
            'toolCallId' => $this->toolCallId
        ];

        if ($this->error !== null) {
            $data['error'] = $this->error;
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
     * Get the message role
     *
     * @return string
     */
    public function getRole(): string
    {
        return 'tool';
    }
}

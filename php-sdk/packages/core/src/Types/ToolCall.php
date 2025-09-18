<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Represents a tool call with function details
 *
 * @package AGUI\Core\Types
 */
final class ToolCall
{
    /**
     * @param string $id Unique identifier for the tool call
     * @param string $type Type of tool call (currently only 'function')
     * @param FunctionCall $function The function to call
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly FunctionCall $function
    ) {
        $this->validate();
    }

    /**
     * Validate the tool call properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('id', v::stringType()->notEmpty())
            ->key('type', v::stringType()->equals('function'))
            ->key('function', v::instance(FunctionCall::class));

        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid tool call data');
        }
    }

    /**
     * Create a ToolCall from an array
     *
     * @param array{ id: string, type: string, function: array{ name: string, arguments: string } } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['id'],
            $data['type'],
            FunctionCall::fromArray($data['function'])
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     function: array{ name: string, arguments: string }
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function->toArray()
        ];
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
}

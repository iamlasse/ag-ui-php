<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Represents context information for the agent
 *
 * @package AGUI\Core\Types
 */
final class Context
{
    /**
     * @param string $description Description of the context
     * @param string $value The context value
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $description,
        public readonly string $value
    ) {
        $this->validate();
    }

    /**
     * Validate the context properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('description', v::stringType()->notEmpty())
            ->key('value', v::stringType());

        $data = [
            'description' => $this->description,
            'value' => $this->value
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid context data');
        }
    }

    /**
     * Create a Context from an array
     *
     * @param array{ description: string, value: string } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['description'],
            $data['value']
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{ description: string, value: string }
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'value' => $this->value
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

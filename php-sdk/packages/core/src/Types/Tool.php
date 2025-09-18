<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Represents a tool that can be called by the agent
 *
 * @package AGUI\Core\Types
 */
final class Tool
{
    /**
     * @param string $name The name of the tool
     * @param string $description Description of what the tool does
     * @param mixed $parameters JSON Schema for the tool parameters
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly mixed $parameters
    ) {
        $this->validate();
    }

    /**
     * Validate the tool properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new ValidationException('Tool name must be a non-empty string');
        }

        if (!v::stringType()->validate($this->description)) {
            throw new ValidationException('Tool description must be a string');
        }

        // Add parameters validation if needed
        if ($this->parameters !== null && !is_array($this->parameters) && !is_object($this->parameters)) {
            throw new ValidationException('Tool parameters must be an array, object, or null');
        }
    }

    /**
     * Create a Tool from an array
     *
     * @param array{ name: string, description: string, parameters: mixed } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $requiredKeys = ['name', 'description', 'parameters'];
        $missingKeys = array_diff($requiredKeys, array_keys($data));

        if (!empty($missingKeys)) {
            throw new ValidationException('Missing required keys: ' . implode(', ', $missingKeys));
        }

        return new static(
            $data['name'],
            $data['description'],
            $data['parameters']
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{ name: string, description: string, parameters: mixed }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters
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

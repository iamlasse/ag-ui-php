<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

/**
 * Represents a function call with name and arguments
 *
 * @package AGUI\Core\Types
 */
final class FunctionCall
{
    /**
     * @param string $name The name of the function to call
     * @param string $arguments JSON string containing the function arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $arguments
    ) {
    }

    /**
     * Create a FunctionCall from an array
     *
     * @param array{ name: string, arguments: string } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !isset($data['arguments'])) {
            throw new \InvalidArgumentException('Missing required keys: name and arguments');
        }
    
        return new static(
            $data['name'],
            $data['arguments']
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{ name: string, arguments: string }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments
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

    /**
     * Create from JSON string
     *
     * @param string $json
     * @return static
     * @throws \JsonException
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON must decode to an array');
        }
        
        return static::fromArray($data);
    }
}

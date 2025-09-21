<?php

declare(strict_types=1);

namespace AGUI\Proto;

/**
 * JSON Patch Operation Constants
 * 
 * This class provides constants for JSON patch operations,
 * maintaining consistency with the TypeScript implementation.
 */
class JsonPatchOperations
{
    public const ADD = 'add';
    public const REMOVE = 'remove';
    public const REPLACE = 'replace';
    public const MOVE = 'move';
    public const COPY = 'copy';
    public const TEST = 'test';

    /**
     * Get all operation types as an array.
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }

    /**
     * Check if an operation type is valid.
     */
    public static function isValid(string $operation): bool
    {
        return in_array($operation, self::all(), true);
    }
}

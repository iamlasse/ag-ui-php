<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

/**
 * Enumeration of possible message roles
 *
 * @package AGUI\Core\Types
 */
enum Role: string
{
    case DEVELOPER = 'developer';
    case SYSTEM = 'system';
    case ASSISTANT = 'assistant';
    case USER = 'user';
    case TOOL = 'tool';

    /**
     * Get all valid roles
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a role is valid
     *
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}

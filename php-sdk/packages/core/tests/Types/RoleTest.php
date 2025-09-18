<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\Role;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\Role
 */
final class RoleTest extends TestCase {
    public function testReturnsAllRoles(): void {
        $roles = Role::all();

        $this->assertSame(['developer', 'system', 'assistant', 'user', 'tool'], $roles);
    }

    public function testValidatesCorrectRoles(): void {
        $this->assertTrue(Role::isValid('developer'));
        $this->assertTrue(Role::isValid('system'));
        $this->assertTrue(Role::isValid('assistant'));
        $this->assertTrue(Role::isValid('user'));
        $this->assertTrue(Role::isValid('tool'));
    }

    public function testInvalidatesIncorrectRoles(): void {
        $this->assertFalse(Role::isValid('unknown'));
        $this->assertFalse(Role::isValid('DEVELOPER')); // Case sensitive
        $this->assertFalse(Role::isValid(''));
        $this->assertFalse(Role::isValid('admin'));
    }

    public function testCasesHaveCorrectValues(): void {
        $this->assertSame('developer', Role::DEVELOPER->value);
        $this->assertSame('system', Role::SYSTEM->value);
        $this->assertSame('assistant', Role::ASSISTANT->value);
        $this->assertSame('user', Role::USER->value);
        $this->assertSame('tool', Role::TOOL->value);
    }
}
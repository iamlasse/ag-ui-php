<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\BaseMessage;
use AGUI\Core\Types\DeveloperMessage;
use AGUI\Core\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\BaseMessage
 */
final class BaseMessageTest extends TestCase {
    public function testBaseMessageProvidesConsistentInterface(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'test-name');

        $this->assertInstanceOf(BaseMessage::class, $message);
        $this->assertSame('test-id', $message->getId());
        $this->assertSame('developer', $message->getRole());
        $this->assertSame('Test content', $message->getContent());
    }

    public function testToArrayReturnsCorrectStructure(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'test-name');
        $result = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'developer',
            'content' => 'Test content',
            'name' => 'test-name'
        ], $result);
    }

    public function testToArrayOmitsNullContent(): void {
        $message = new class('test-id', 'developer', null, 'test-name') extends BaseMessage {
            protected function validate(): void {}
        };

        $result = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'developer',
            'name' => 'test-name'
        ], $result);
        $this->assertArrayNotHasKey('content', $result);
    }

    public function testToArrayOmitsNullName(): void {
        $message = new class('test-id', 'developer', 'Test content', null) extends BaseMessage {
            protected function validate(): void {}
        };

        $result = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'developer',
            'content' => 'Test content'
        ], $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function testGettersReturnCorrectValues(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'test-name');

        $this->assertSame('test-id', $message->getId());
        $this->assertSame('developer', $message->getRole());
        $this->assertSame('Test content', $message->getContent());
    }
}
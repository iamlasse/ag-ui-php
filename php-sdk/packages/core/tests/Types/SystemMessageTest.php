<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\SystemMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\SystemMessage
 */
final class SystemMessageTest extends TestCase {
    public function testCanBeCreated(): void {
        $message = new SystemMessage('test-id', 'Test content');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('system', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }

    public function testCanBeCreatedWithName(): void {
        $message = new SystemMessage('test-id', 'Test content', 'system-name');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('system', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('system-name', $message->name);
    }

    public function testCanBeConvertedToArray(): void {
        $message = new SystemMessage('test-id', 'Test content', 'system-name');
        $array = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'system',
            'content' => 'Test content',
            'name' => 'system-name'
        ], $array);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content',
            'name' => 'system-name'
        ];

        $message = SystemMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('system', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('system-name', $message->name);
    }

    public function testCanBeCreatedFromArrayWithoutName(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content'
        ];

        $message = SystemMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('system', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }
}
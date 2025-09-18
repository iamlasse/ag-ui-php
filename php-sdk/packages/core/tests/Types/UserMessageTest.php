<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\UserMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\UserMessage
 */
final class UserMessageTest extends TestCase {
    public function testCanBeCreated(): void {
        $message = new UserMessage('test-id', 'Test content');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('user', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }

    public function testCanBeCreatedWithName(): void {
        $message = new UserMessage('test-id', 'Test content', 'user-name');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('user', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('user-name', $message->name);
    }

    public function testCanBeConvertedToArray(): void {
        $message = new UserMessage('test-id', 'Test content', 'user-name');
        $array = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'user',
            'content' => 'Test content',
            'name' => 'user-name'
        ], $array);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content',
            'name' => 'user-name'
        ];

        $message = UserMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('user', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('user-name', $message->name);
    }

    public function testCanBeCreatedFromArrayWithoutName(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content'
        ];

        $message = UserMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('user', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }
}
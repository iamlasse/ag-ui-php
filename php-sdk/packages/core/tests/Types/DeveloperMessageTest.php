<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\DeveloperMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\DeveloperMessage
 */
final class DeveloperMessageTest extends TestCase {
    public function testCanBeCreated(): void {
        $message = new DeveloperMessage('test-id', 'Test content');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('developer', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }

    public function testCanBeCreatedWithName(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'developer-name');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('developer', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('developer-name', $message->name);
    }

    public function testCanBeConvertedToArray(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'developer-name');
        $array = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'developer',
            'content' => 'Test content',
            'name' => 'developer-name'
        ], $array);
    }

    public function testCanBeConvertedToArrayWithoutName(): void {
        $message = new DeveloperMessage('test-id', 'Test content');
        $array = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'role' => 'developer',
            'content' => 'Test content'
        ], $array);
        $this->assertArrayNotHasKey('name', $array);
    }

    public function testCanBeConvertedToJson(): void {
        $message = new DeveloperMessage('test-id', 'Test content', 'developer-name');
        $json = $message->toJson();

        $this->assertJson($json);
        $this->assertSame('{"id":"test-id","role":"developer","content":"Test content","name":"developer-name"}', $json);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content',
            'name' => 'developer-name'
        ];

        $message = DeveloperMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('developer', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('developer-name', $message->name);
    }

    public function testCanBeCreatedFromArrayWithoutName(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content'
        ];

        $message = DeveloperMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('developer', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
    }
}
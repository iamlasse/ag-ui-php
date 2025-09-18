<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\ToolMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\ToolMessage
 */
final class ToolMessageTest extends TestCase {
    public function testCanBeCreated(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
        $this->assertNull($message->error);
    }

    public function testCanBeCreatedWithError(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id', 'Tool failed');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
        $this->assertSame('Tool failed', $message->error);
    }

    public function testGetRole(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id');

        $this->assertSame('tool', $message->getRole());
    }

    public function testCanBeConvertedToArray(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id', 'Tool failed');
        $array = $message->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'content' => 'Tool result',
            'role' => 'tool',
            'toolCallId' => 'tool-call-id',
            'error' => 'Tool failed'
        ], $array);
    }

    public function testToArrayOmitsNullError(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id');
        $array = $message->toArray();

        $this->assertArrayNotHasKey('error', $array);
    }

    public function testCanBeConvertedToJson(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id', 'Tool failed');
        $json = $message->toJson();

        $this->assertJson($json);
        $expected = '{"id":"test-id","content":"Tool result","role":"tool","toolCallId":"tool-call-id","error":"Tool failed"}';
        $this->assertSame($expected, $json);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Tool result',
            'toolCallId' => 'tool-call-id',
            'error' => 'Tool failed'
        ];

        $message = ToolMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
        $this->assertSame('Tool failed', $message->error);
    }

    public function testCanBeCreatedFromArrayWithoutError(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Tool result',
            'toolCallId' => 'tool-call-id'
        ];

        $message = ToolMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
        $this->assertNull($message->error);
    }
}
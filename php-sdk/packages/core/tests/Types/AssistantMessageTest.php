<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\AssistantMessage;
use AGUI\Core\Types\FunctionCall;
use AGUI\Core\Types\ToolCall;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\AssistantMessage
 */
final class AssistantMessageTest extends TestCase {
    public function testCanBeCreated(): void {
        $message = new AssistantMessage('test-id', 'Test content');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->toolCalls);
    }

    public function testCanBeCreatedWithoutContent(): void {
        $message = new AssistantMessage('test-id');

        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertNull($message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->toolCalls);
    }

    public function testCanBeCreatedWithToolCalls(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('tool-call-id', 'function', $functionCall);
        $message = new AssistantMessage('test-id', 'Test content', [$toolCall]);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertIsArray($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);
        $this->assertInstanceOf(ToolCall::class, $message->toolCalls[0]);
    }

    public function testGetToolCalls(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('tool-call-id', 'function', $functionCall);
        $message = new AssistantMessage('test-id', 'Test content', [$toolCall]);

        $toolCalls = $message->getToolCalls();

        $this->assertIsArray($toolCalls);
        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
    }

    public function testGetToolCallsReturnsNullWhenNoToolCalls(): void {
        $message = new AssistantMessage('test-id', 'Test content');

        $this->assertNull($message->getToolCalls());
    }

    public function testCanBeConvertedToArray(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('tool-call-id', 'function', $functionCall);
        $message = new AssistantMessage('test-id', 'Test content', [$toolCall], 'assistant-name');

        $array = $message->toArray();

        $this->assertSame('test-id', $array['id']);
        $this->assertSame('assistant', $array['role']);
        $this->assertSame('Test content', $array['content']);
        $this->assertSame('assistant-name', $array['name']);
        $this->assertArrayHasKey('toolCalls', $array);
        $this->assertCount(1, $array['toolCalls']);
    }

    public function testToArrayOmitsNullContent(): void {
        $message = new AssistantMessage('test-id', null);

        $array = $message->toArray();

        $this->assertArrayNotHasKey('content', $array);
    }

    public function testToArrayOmitsNullToolCalls(): void {
        $message = new AssistantMessage('test-id', 'Test content');

        $array = $message->toArray();

        $this->assertArrayNotHasKey('toolCalls', $array);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content',
            'name' => 'assistant-name',
            'toolCalls' => [
                [
                    'id' => 'tool-call-id',
                    'type' => 'function',
                    'function' => [
                        'name' => 'test_function',
                        'arguments' => '{"arg": "value"}'
                    ]
                ]
            ]
        ];

        $message = AssistantMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertSame('assistant-name', $message->name);
        $this->assertIsArray($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);
    }

    public function testCanBeCreatedFromArrayWithoutOptionalFields(): void {
        $data = [
            'id' => 'test-id',
            'content' => 'Test content'
        ];

        $message = AssistantMessage::fromArray($data);

        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->toolCalls);
    }
}
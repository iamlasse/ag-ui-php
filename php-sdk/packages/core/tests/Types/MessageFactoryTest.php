<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\AssistantMessage;
use AGUI\Core\Types\DeveloperMessage;
use AGUI\Core\Types\MessageFactory;
use AGUI\Core\Types\SystemMessage;
use AGUI\Core\Types\ToolCall;
use AGUI\Core\Types\ToolMessage;
use AGUI\Core\Types\UserMessage;
use AGUI\Core\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\MessageFactory
 */
final class MessageFactoryTest extends TestCase {
    public function testCreatesDeveloperMessage(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'developer',
            'content' => 'Test content'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(DeveloperMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('developer', $message->role);
        $this->assertSame('Test content', $message->content);
    }

    public function testCreatesSystemMessage(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'system',
            'content' => 'Test content'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(SystemMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('system', $message->role);
        $this->assertSame('Test content', $message->content);
    }

    public function testCreatesUserMessage(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'user',
            'content' => 'Test content'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('user', $message->role);
        $this->assertSame('Test content', $message->content);
    }

    public function testCreatesAssistantMessage(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'assistant',
            'content' => 'Test content'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
    }

    public function testCreatesAssistantMessageWithToolCalls(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'assistant',
            'content' => 'Test content',
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

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('assistant', $message->role);
        $this->assertSame('Test content', $message->content);
        $this->assertIsArray($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);
        $this->assertInstanceOf(ToolCall::class, $message->toolCalls[0]);
    }

    public function testCreatesToolMessage(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'tool',
            'content' => 'Tool result',
            'toolCallId' => 'tool-call-id'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(ToolMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
    }

    public function testCreatesToolMessageWithError(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'tool',
            'content' => 'Tool result',
            'toolCallId' => 'tool-call-id',
            'error' => 'Tool failed'
        ];

        $message = MessageFactory::fromArray($data);

        $this->assertInstanceOf(ToolMessage::class, $message);
        $this->assertSame('test-id', $message->id);
        $this->assertSame('tool', $message->getRole());
        $this->assertSame('Tool result', $message->content);
        $this->assertSame('tool-call-id', $message->toolCallId);
        $this->assertSame('Tool failed', $message->error);
    }

    public function testThrowsExceptionWithUnknownRole(): void {
        $data = [
            'id' => 'test-id',
            'role' => 'unknown',
            'content' => 'Test content'
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unknown message role: unknown');
        MessageFactory::fromArray($data);
    }

    public function testCreatesMultipleMessages(): void {
        $messagesData = [
            [
                'id' => 'message-1',
                'role' => 'developer',
                'content' => 'Developer message'
            ],
            [
                'id' => 'message-2',
                'role' => 'user',
                'content' => 'User message'
            ],
            [
                'id' => 'message-3',
                'role' => 'tool',
                'content' => 'Tool result',
                'toolCallId' => 'tool-call-id'
            ]
        ];

        $messages = MessageFactory::fromArrayMultiple($messagesData);

        $this->assertCount(3, $messages);
        $this->assertInstanceOf(DeveloperMessage::class, $messages[0]);
        $this->assertInstanceOf(UserMessage::class, $messages[1]);
        $this->assertInstanceOf(ToolMessage::class, $messages[2]);
    }
}
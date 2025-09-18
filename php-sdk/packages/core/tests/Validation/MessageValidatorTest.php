<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Validation;

use AGUI\Core\Types\AssistantMessage;
use AGUI\Core\Types\DeveloperMessage;
use AGUI\Core\Types\SystemMessage;
use AGUI\Core\Types\ToolCall;
use AGUI\Core\Types\ToolMessage;
use AGUI\Core\Types\UserMessage;
use AGUI\Core\Validation\MessageValidator;
use AGUI\Core\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Validation\MessageValidator
 */
final class MessageValidatorTest extends TestCase {
    private MessageValidator $validator;

    protected function setUp(): void {
        $this->validator = new MessageValidator();
    }

    public function testValidatesDeveloperMessage(): void {
        $message = new DeveloperMessage('test-id', 'Test content');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesSystemMessage(): void {
        $message = new SystemMessage('test-id', 'Test content');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesUserMessage(): void {
        $message = new UserMessage('test-id', 'Test content');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesAssistantMessage(): void {
        $message = new AssistantMessage('test-id', 'Test content');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesAssistantMessageWithToolCalls(): void {
        $toolCall = new ToolCall('tool-call-id', 'function', new \AGUI\Core\Types\FunctionCall('test_function', '{"arg": "value"}'));
        $message = new AssistantMessage('test-id', 'Test content', [$toolCall]);

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesToolMessage(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidatesToolMessageWithError(): void {
        $message = new ToolMessage('test-id', 'Tool result', 'tool-call-id', 'Tool failed');

        $this->assertTrue($this->validator->validate($message));

        $this->validator->validateMessage($message); // Should not throw
    }

    public function testValidateReturnsFalseForInvalidMessage(): void {
        // Create a mock message that will fail validation
        $message = new class('test-id', 'invalid-role', 'Test content') extends \AGUI\Core\Types\BaseMessage {
            protected function validate(): void {}
            public function getRole(): string {
                return 'invalid-role';
            }
        };

        $this->assertFalse($this->validator->validate($message));
    }

    public function testValidateMessageThrowsForInvalidRole(): void {
        // Create a mock message with invalid role
        $message = new class('test-id', 'invalid-role', 'Test content') extends \AGUI\Core\Types\BaseMessage {
            protected function validate(): void {}
            public function getRole(): string {
                return 'invalid-role';
            }
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unknown message role: invalid-role');
        $this->validator->validateMessage($message);
    }

    public function testValidateReturnsFalseForInvalidStructure(): void {
        // This test demonstrates the difference between validate() and validateMessage()
        // validate() catches exceptions, validateMessage() throws them

        // Create a mock message with invalid structure that will fail validation
        $message = new class('test-id', 'invalid-role', 'Test content') extends \AGUI\Core\Types\BaseMessage {
            protected function validate(): void {}
            public function getRole(): string {
                return 'invalid-role';
            }
        };

        // validate() should return false due to invalid role
        $this->assertFalse($this->validator->validate($message));

        // validateMessage() should throw the exception
        $this->expectException(ValidationException::class);
        $this->validator->validateMessage($message);
    }
}
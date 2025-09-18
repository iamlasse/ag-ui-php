<?php

declare(strict_types=1);

namespace AGUI\Core\Validation;

use AGUI\Core\Types\BaseMessage;
use AGUI\Core\Types\ToolMessage;
use Respect\Validation\Validator as v;

/**
 * Validator for message types
 *
 * @package AGUI\Core\Validation
 */
final class MessageValidator
{
    /**
     * Validate a message instance
     *
     * @param BaseMessage|ToolMessage $message
     * @return bool
     */
    public function validate(BaseMessage|ToolMessage $message): bool
    {
        try {
            $this->validateMessage($message);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Validate a message and throw exception if invalid
     *
     * @param BaseMessage|ToolMessage $message
     * @throws ValidationException
     */
    public function validateMessage(BaseMessage|ToolMessage $message): void
    {
        $data = $message->toArray();

        switch ($message->getRole()) {
            case 'developer':
                $this->validateDeveloperMessage($data);
                break;
            case 'system':
                $this->validateSystemMessage($data);
                break;
            case 'assistant':
                $this->validateAssistantMessage($data);
                break;
            case 'user':
                $this->validateUserMessage($data);
                break;
            case 'tool':
                $this->validateToolMessage($data);
                break;
            default:
                throw new ValidationException("Unknown message role: {$message->getRole()}");
        }
    }

    /**
     * Validate developer message data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateDeveloperMessage(array $data): void
    {
        // Check required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('Invalid developer message: missing or empty id');
        }

        if (!isset($data['role']) || $data['role'] !== 'developer') {
            throw new ValidationException('Invalid developer message: invalid role');
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            throw new ValidationException('Invalid developer message: missing or invalid content');
        }

        // Name is optional but must be a string if present
        if (isset($data['name']) && !is_string($data['name'])) {
            throw new ValidationException('Invalid developer message: invalid name');
        }
    }

    /**
     * Validate system message data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateSystemMessage(array $data): void
    {
        // Check required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('Invalid system message: missing or empty id');
        }

        if (!isset($data['role']) || $data['role'] !== 'system') {
            throw new ValidationException('Invalid system message: invalid role');
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            throw new ValidationException('Invalid system message: missing or invalid content');
        }

        // Name is optional but must be a string if present
        if (isset($data['name']) && !is_string($data['name'])) {
            throw new ValidationException('Invalid system message: invalid name');
        }
    }

    /**
     * Validate assistant message data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateAssistantMessage(array $data): void
    {
        // Check required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('Invalid assistant message: missing or empty id');
        }

        if (!isset($data['role']) || $data['role'] !== 'assistant') {
            throw new ValidationException('Invalid assistant message: invalid role');
        }

        // Content is optional but must be a string if present
        if (isset($data['content']) && !is_string($data['content'])) {
            throw new ValidationException('Invalid assistant message: invalid content');
        }

        // Name is optional but must be a string if present
        if (isset($data['name']) && !is_string($data['name'])) {
            throw new ValidationException('Invalid assistant message: invalid name');
        }

        // Validate tool calls if present
        if (isset($data['toolCalls']) && is_array($data['toolCalls'])) {
            foreach ($data['toolCalls'] as $toolCall) {
                $this->validateToolCall($toolCall);
            }
        }
    }

    /**
     * Validate user message data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateUserMessage(array $data): void
    {
        // Check required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('Invalid user message: missing or empty id');
        }

        if (!isset($data['role']) || $data['role'] !== 'user') {
            throw new ValidationException('Invalid user message: invalid role');
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            throw new ValidationException('Invalid user message: missing or invalid content');
        }

        // Name is optional but must be a string if present
        if (isset($data['name']) && !is_string($data['name'])) {
            throw new ValidationException('Invalid user message: invalid name');
        }
    }

    /**
     * Validate tool message data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateToolMessage(array $data): void
    {
        // Check required fields
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('Invalid tool message: missing or empty id');
        }

        if (!isset($data['role']) || $data['role'] !== 'tool') {
            throw new ValidationException('Invalid tool message: invalid role');
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            throw new ValidationException('Invalid tool message: missing or invalid content');
        }

        if (!isset($data['toolCallId']) || !is_string($data['toolCallId']) || $data['toolCallId'] === '') {
            throw new ValidationException('Invalid tool message: missing or empty toolCallId');
        }

        // Error is optional but must be a string if present
        if (isset($data['error']) && !is_string($data['error'])) {
            throw new ValidationException('Invalid tool message: invalid error');
        }
    }

    /**
     * Validate tool call data
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateToolCall(array $data): void
    {
        $validator = v::key('id', v::stringType()->notEmpty())
            ->key('type', v::stringType()->equals('function'))
            ->key('function', v::arrayType()
                ->key('name', v::stringType()->notEmpty())
                ->key('arguments', v::stringType()));

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid tool call');
        }
    }
}

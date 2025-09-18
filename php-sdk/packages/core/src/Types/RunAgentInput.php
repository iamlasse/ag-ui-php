<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;

/**
 * Input data for running an agent
 *
 * @package AGUI\Core\Types
 */
final class RunAgentInput
{
    /**
     * @param string $threadId The thread ID
     * @param string $runId The run ID
     * @param mixed $state The current state
     * @param array<int, BaseMessage|ToolMessage> $messages Array of messages
     * @param array<int, Tool> $tools Array of available tools
     * @param array<int, Context> $context Array of context information
     * @param mixed $forwardedProps Additional forwarded properties
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $threadId,
        public readonly string $runId,
        public readonly mixed $state,
        public readonly array $messages,
        public readonly array $tools,
        public readonly array $context,
        public readonly mixed $forwardedProps
    ) {
        $this->validate();
    }

    /**
     * Validate the run agent input properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('threadId', v::stringType()->notEmpty())
            ->key('runId', v::stringType()->notEmpty())
            ->key('messages', v::arrayType())
            ->key('tools', v::arrayType())
            ->key('context', v::arrayType());

        $data = [
            'threadId' => $this->threadId,
            'runId' => $this->runId,
            'messages' => $this->messages,
            'tools' => $this->tools,
            'context' => $this->context
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid run agent input data');
        }

        // Validate message types
        foreach ($this->messages as $message) {
            if (!$message instanceof \AGUI\Core\Types\BaseMessage && !$message instanceof \AGUI\Core\Types\ToolMessage) {
                throw new ValidationException('All messages must be instances of BaseMessage or ToolMessage');
            }
        }

        // Validate tool types
        foreach ($this->tools as $tool) {
            if (!$tool instanceof \AGUI\Core\Types\Tool) {
                throw new ValidationException('All tools must be instances of Tool');
            }
        }

        // Validate context types
        foreach ($this->context as $context) {
            if (!$context instanceof \AGUI\Core\Types\Context) {
                throw new ValidationException('All context items must be instances of Context');
            }
        }
    }

    /**
     * Create a RunAgentInput from an array
     *
     * @param array{
     *     threadId: string,
     *     runId: string,
     *     state: mixed,
     *     messages: array<int, array>,
     *     tools: array<int, array>,
     *     context: array<int, array>,
     *     forwardedProps: mixed
     * } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $messages = MessageFactory::fromArrayMultiple($data['messages']);
        $tools = array_map(fn($tool) => Tool::fromArray($tool), $data['tools']);
        $context = array_map(fn($context) => Context::fromArray($context), $data['context']);

        return new static(
            $data['threadId'],
            $data['runId'],
            $data['state'],
            $messages,
            $tools,
            $context,
            $data['forwardedProps']
        );
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     threadId: string,
     *     runId: string,
     *     state: mixed,
     *     messages: array<int, array>,
     *     tools: array<int, array>,
     *     context: array<int, array>,
     *     forwardedProps: mixed
     * }
     */
    public function toArray(): array
    {
        return [
            'threadId' => $this->threadId,
            'runId' => $this->runId,
            'state' => $this->state,
            'messages' => array_map(fn($message) => $message->toArray(), $this->messages),
            'tools' => array_map(fn($tool) => $tool->toArray(), $this->tools),
            'context' => array_map(fn($context) => $context->toArray(), $this->context),
            'forwardedProps' => $this->forwardedProps
        ];
    }

    /**
     * Convert to JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}

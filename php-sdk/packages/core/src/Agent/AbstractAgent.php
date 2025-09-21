<?php

declare(strict_types=1);

namespace AGUI\Core\Agent;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunAgentInput;

/**
 * Abstract base class for all AG-UI agents
 *
 * @package AGUI\Core\Agent
 */
abstract class AbstractAgent
{
    protected ?string $agentId = null;
    protected string $description;
    protected string $threadId;
    protected array $messages = [];
    protected mixed $state = null;
    protected bool $debug = false;
    protected array $subscribers = [];

    /**
     * AbstractAgent constructor
     *
     * @param array{
     *     agentId?: string,
     *     description?: string,
     *     threadId?: string,
     *     initialMessages?: array,
     *     initialState?: mixed,
     *     debug?: bool
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->agentId = $config['agentId'] ?? null;
        $this->description = $config['description'] ?? '';
        $this->threadId = $config['threadId'] ?? $this->generateUuid();
        $this->messages = $config['initialMessages'] ?? [];
        $this->state = $config['initialState'] ?? null;
        $this->debug = $config['debug'] ?? false;
    }

    /**
     * Abstract method to run the agent with the given input
     * Must be implemented by concrete agent classes
     *
     * @param RunAgentInput $input The input for running the agent
     * @return EventObservable Observable stream of events
     */
    abstract public function run(RunAgentInput $input): EventObservable;

    /**
     * Subscribe to agent events
     *
     * @param callable $subscriber The subscriber callback
     * @return array{unsubscribe: callable}
     */
    public function subscribe(callable $subscriber): array
    {
        $this->subscribers[] = $subscriber;

        return [
            'unsubscribe' => function () use ($subscriber) {
                $this->subscribers = array_filter($this->subscribers, fn($s) => $s !== $subscriber);
            }
        ];
    }

    /**
     * Get the agent ID
     *
     * @return string|null
     */
    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    /**
     * Get the agent description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the thread ID
     *
     * @return string
     */
    public function getThreadId(): string
    {
        return $this->threadId;
    }

    /**
     * Get the current messages
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the current state
     *
     * @return mixed
     */
    public function getState(): mixed
    {
        return $this->state;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Add a message to the agent
     *
     * @param mixed $message The message to add
     * @return void
     */
    public function addMessage(mixed $message): void
    {
        $this->messages[] = $message;
        $this->notifySubscribers('onNewMessage', ['message' => $message]);
    }

    /**
     * Add multiple messages to the agent
     *
     * @param array $messages The messages to add
     * @return void
     */
    public function addMessages(array $messages): void
    {
        $this->messages = array_merge($this->messages, $messages);
        $this->notifySubscribers('onNewMessages', ['messages' => $messages]);
    }

    /**
     * Set the messages for the agent
     *
     * @param array $messages The messages to set
     * @return void
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
        $this->notifySubscribers('onMessagesChanged', ['messages' => $messages]);
    }

    /**
     * Set the state for the agent
     *
     * @param mixed $state The state to set
     * @return void
     */
    public function setState(mixed $state): void
    {
        $this->state = $state;
        $this->notifySubscribers('onStateChanged', ['state' => $state]);
    }

    /**
     * Clone the agent
     *
     * @return static
     */
    public function clone(): static
    {
        return new static([
            'agentId' => $this->agentId ? $this->generateUuid() : null,
            'description' => $this->description,
            'threadId' => $this->generateUuid(),
            'initialMessages' => $this->messages,
            'initialState' => $this->state,
            'debug' => $this->debug
        ]);
    }

    /**
     * Notify all subscribers of an event
     *
     * @param string $event The event name
     * @param array $data The event data
     * @return void
     */
    protected function notifySubscribers(string $event, array $data): void
    {
        foreach ($this->subscribers as $subscriber) {
            try {
                if (is_callable($subscriber)) {
                    $subscriber($event, $data);
                }
            } catch (\Throwable $e) {
                if ($this->debug) {
                    error_log("Subscriber error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Generate a UUID
     *
     * @return string
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
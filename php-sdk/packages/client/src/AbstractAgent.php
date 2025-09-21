<?php

declare(strict_types=1);

namespace AGUI\Client;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;

/**
 * Abstract agent interface for AG-UI protocol implementations
 *
 * @package AGUI\Client
 */
abstract class AbstractAgent
{
    /**
     * Run the agent with the provided configuration
     *
     * @param RunConfig $config The configuration for the agent run
     * @return EventObservable Observable stream of events
     */
    abstract public function run(RunConfig $config): EventObservable;

    /**
     * Stop the agent execution
     *
     * @return void
     */
    abstract public function stop(): void;

    /**
     * Check if the agent is currently running
     *
     * @return bool True if running, false otherwise
     */
    abstract public function isRunning(): bool;

    /**
     * Get the agent's unique identifier
     *
     * @return string
     */
    abstract public function getId(): string;

    /**
     * Get the agent's configuration
     *
     * @return array<string, mixed>
     */
    abstract public function getConfig(): array;
}

<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;

/**
 * Interface for different transport mechanisms
 *
 * @package AGUI\Client\Transport
 */
interface TransportInterface
{
    /**
     * Connect to the transport endpoint
     *
     * @param string $endpoint The endpoint URL
     * @param array<string, mixed> $options Additional options
     * @return void
     */
    public function connect(string $endpoint, array $options = []): void;

    /**
     * Send data through the transport
     *
     * @param string $data The data to send
     * @return void
     */
    public function send(string $data): void;

    /**
     * Start streaming and return an observable of events
     *
     * @param RunConfig $config The run configuration
     * @return EventObservable Observable stream of events
     */
    public function stream(RunConfig $config): EventObservable;

    /**
     * Disconnect from the transport
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Check if transport is connected
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool;

    /**
     * Get the transport type
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get transport-specific configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;
}

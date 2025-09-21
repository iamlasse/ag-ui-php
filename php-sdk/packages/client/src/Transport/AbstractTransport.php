<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for transport implementations
 *
 * @package AGUI\Client\Transport
 */
abstract class AbstractTransport implements TransportInterface
{
    protected ClientInterface $httpClient;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;
    protected LoggerInterface $logger;
    protected array $config;
    protected bool $connected = false;
    protected ?string $endpoint = null;

    /**
     * AbstractTransport constructor
     *
     * @param ClientInterface $httpClient HTTP client implementation
     * @param RequestFactoryInterface $requestFactory Request factory implementation
     * @param StreamFactoryInterface $streamFactory Stream factory implementation
     * @param array<string, mixed> $config Transport configuration
     * @param LoggerInterface|null $logger Logger implementation
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function connect(string $endpoint, array $options = []): void
    {
        if ($this->connected) {
            throw new \RuntimeException('Transport is already connected');
        }

        $this->endpoint = $endpoint;
        $this->config = array_merge($this->config, $options);

        $this->logger->debug('Connecting transport', [
            'type' => $this->getType(),
            'endpoint' => $endpoint,
            'options' => $options
        ]);

        $this->doConnect();
        $this->connected = true;

        $this->logger->info('Transport connected', [
            'type' => $this->getType(),
            'endpoint' => $endpoint
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->logger->debug('Disconnecting transport', [
            'type' => $this->getType(),
            'endpoint' => $this->endpoint
        ]);

        $this->doDisconnect();
        $this->connected = false;
        $this->endpoint = null;

        $this->logger->info('Transport disconnected', [
            'type' => $this->getType()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the current endpoint
     *
     * @return string|null
     */
    protected function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * Get the HTTP client
     *
     * @return ClientInterface
     */
    protected function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    /**
     * Get the request factory
     *
     * @return RequestFactoryInterface
     */
    protected function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    /**
     * Get the stream factory
     *
     * @return StreamFactoryInterface
     */
    protected function getStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Perform the actual connection
     *
     * @return void
     */
    abstract protected function doConnect(): void;

    /**
     * Perform the actual disconnection
     *
     * @return void
     */
    abstract protected function doDisconnect(): void;

    /**
     * Get default transport configuration
     *
     * @return array<string, mixed>
     */
    abstract protected function getDefaultConfig(): array;
}

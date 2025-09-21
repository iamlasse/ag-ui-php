<?php

declare(strict_types=1);

namespace AGUI\Client;

use AGUI\Client\Transport\TransportInterface;
use AGUI\Client\Transport\TransportFactory;
use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Events\BaseEvent;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

/**
 * HTTP Agent implementation for AG-UI protocol
 *
 * @package AGUI\Client
 */
class HttpAgent extends AbstractAgent
{
    private string $id;
    private array $config;
    private bool $running = false;
    private ?TransportInterface $transport = null;
    private ?EventObservable $observable = null;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private TransportFactory $transportFactory;

    /**
     * HttpAgent constructor
     *
     * @param ClientInterface $httpClient HTTP client implementation
     * @param RequestFactoryInterface $requestFactory Request factory implementation
     * @param StreamFactoryInterface $streamFactory Stream factory implementation
     * @param TransportFactory|null $transportFactory Transport factory (optional)
     * @param LoggerInterface|null $logger Logger implementation (optional)
     * @param array<string, mixed> $config Agent configuration
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?TransportFactory $transportFactory = null,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->transportFactory = $transportFactory ?? new TransportFactory($httpClient, $requestFactory, $streamFactory);
        $this->logger = $logger ?? new NullLogger();
        $this->config = array_merge($this->getDefaultConfig(), $config);

        $this->logger->debug('HttpAgent initialized', [
            'id' => $this->id,
            'config' => $this->config
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function run(RunConfig $runConfig): EventObservable
    {
        if ($this->running) {
            throw new \RuntimeException('Agent is already running');
        }

        $this->running = true;
        $this->observable = new EventObservable();

        try {
            $this->logger->info('Starting HTTP agent', [
                'id' => $this->id,
                'run_id' => $runConfig->getRunId()
            ]);

            // Create and configure transport
            $this->transport = $this->createTransport($runConfig);
            $this->transport->connect($runConfig->getEndpoint(), $runConfig->getTransportOptions());

            // Start streaming events
            $transportObservable = $this->transport->stream($runConfig);

            // Pipe transport events to our observable
            $transportObservable->subscribe(
                [$this->observable, 'emitEvent'],
                [$this->observable, 'emitError'],
                [$this->observable, 'complete']
            );

            $this->logger->info('HTTP agent started successfully', [
                'id' => $this->id,
                'transport' => $this->transport->getType()
            ]);

        } catch (\Throwable $error) {
            $this->running = false;
            $this->logger->error('Failed to start HTTP agent', [
                'id' => $this->id,
                'error' => $error->getMessage()
            ]);

            if ($this->observable) {
                $this->observable->emitError($error);
            }
            throw $error;
        }

        return $this->observable;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        $this->logger->info('Stopping HTTP agent', ['id' => $this->id]);

        $this->running = false;

        if ($this->transport) {
            $this->transport->disconnect();
            $this->transport = null;
        }

        if ($this->observable) {
            $this->observable->complete();
            $this->observable = null;
        }

        $this->logger->info('HTTP agent stopped', ['id' => $this->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the current transport instance
     *
     * @return TransportInterface|null
     */
    public function getTransport(): ?TransportInterface
    {
        return $this->transport;
    }

    /**
     * Send data through the current transport
     *
     * @param string $data The data to send
     * @throws \RuntimeException If no transport is connected
     */
    public function send(string $data): void
    {
        if (!$this->transport || !$this->transport->isConnected()) {
            throw new \RuntimeException('No active transport connection');
        }

        $this->logger->debug('Sending data through transport', [
            'id' => $this->id,
            'transport' => $this->transport->getType(),
            'data_length' => strlen($data)
        ]);

        $this->transport->send($data);
    }

    /**
     * Create transport based on run configuration
     *
     * @param RunConfig $runConfig The run configuration
     * @return TransportInterface
     * @throws \RuntimeException If transport type is not supported
     */
    private function createTransport(RunConfig $runConfig): TransportInterface
    {
        $transportType = $runConfig->getTransportType() ?? $this->config['default_transport'];

        $this->logger->debug('Creating transport', [
            'id' => $this->id,
            'type' => $transportType
        ]);

        return $this->transportFactory->create($transportType, $runConfig->getTransportOptions());
    }

    /**
     * Get default agent configuration
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'default_transport' => 'sse',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
            'buffer_size' => 8192,
            'headers' => [
                'User-Agent' => 'AG-UI PHP Client/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ];
    }
}

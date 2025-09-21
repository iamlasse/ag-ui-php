<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Events\BaseEvent;
use AGUI\Encoder\EncoderFactory;
use React\EventLoop\Loop;

/**
 * HTTP Binary transport implementation for high-performance binary data transfer
 *
 * @package AGUI\Client\Transport
 */
class HttpBinaryTransport extends AbstractTransport
{
    private ?EventObservable $observable = null;
    private bool $streaming = false;

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'http-binary';
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $data): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Transport not connected');
        }

        $sendEndpoint = $this->config['send_endpoint'] ?? $this->endpoint . '/send';
        
        $request = $this->requestFactory->createRequest('POST', $sendEndpoint);
        $request = $request->withBody($this->streamFactory->createStream($data));
        $request = $this->addHeaders($request, 'application/octet-stream');

        try {
            $this->logger->debug('Sending binary data via HTTP', [
                'endpoint' => $sendEndpoint,
                'data_length' => strlen($data)
            ]);

            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException(
                    sprintf('Send request failed with status %d', $response->getStatusCode())
                );
            }
        } catch (\Throwable $error) {
            $this->logger->error('Failed to send binary data via HTTP', [
                'error' => $error->getMessage()
            ]);
            throw $error;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stream(RunConfig $config): EventObservable
    {
        if (!$this->connected) {
            throw new \RuntimeException('Transport not connected');
        }

        $this->observable = new EventObservable();
        $this->streaming = true;

        // Start polling in a non-blocking way
        Loop::futureTick(function () use ($config) {
            $this->startPolling($config);
        });

        return $this->observable;
    }

    /**
     * {@inheritdoc}
     */
    protected function doConnect(): void
    {
        // Connection validation via health check
        $this->performHealthCheck();
    }

    /**
     * {@inheritdoc}
     */
    protected function doDisconnect(): void
    {
        $this->streaming = false;

        if ($this->observable) {
            $this->observable->complete();
            $this->observable = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig(): array
    {
        return [
            'timeout' => 30,
            'poll_interval' => 1000, // milliseconds
            'max_chunk_size' => 65536,
            'compression' => 'gzip',
            'headers' => [
                'Accept' => 'application/octet-stream',
                'Content-Type' => 'application/octet-stream',
                'Accept-Encoding' => 'gzip, deflate'
            ]
        ];
    }

    /**
     * Perform health check to validate connection
     *
     * @return void
     * @throws \RuntimeException If health check fails
     */
    private function performHealthCheck(): void
    {
        $healthEndpoint = $this->endpoint . '/health';
        
        try {
            $request = $this->requestFactory->createRequest('GET', $healthEndpoint);
            $request = $this->addHeaders($request);

            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(
                    sprintf('Health check failed with status %d', $response->getStatusCode())
                );
            }

            $this->logger->debug('HTTP Binary transport health check passed', [
                'endpoint' => $healthEndpoint
            ]);

        } catch (\Throwable $error) {
            $this->logger->error('HTTP Binary transport health check failed', [
                'error' => $error->getMessage()
            ]);
            throw $error;
        }
    }

    /**
     * Start polling for events
     *
     * @param RunConfig $config Run configuration
     * @return void
     */
    private function startPolling(RunConfig $config): void
    {
        $pollEndpoint = $this->endpoint . '/poll';
        $lastEventId = null;

        $this->logger->info('Starting HTTP Binary polling', [
            'endpoint' => $pollEndpoint,
            'run_id' => $config->getRunId(),
            'interval' => $this->config['poll_interval']
        ]);

        $timer = Loop::addPeriodicTimer(
            $this->config['poll_interval'] / 1000,
            function () use ($pollEndpoint, $config, &$lastEventId) {
                if (!$this->streaming || !$this->observable) {
                    return;
                }

                $this->pollForEvents($pollEndpoint, $config, $lastEventId);
            }
        );

        // Cleanup timer when observable completes
        $this->observable->subscribe(
            null,
            null,
            function () use ($timer) {
                Loop::cancelTimer($timer);
            }
        );
    }

    /**
     * Poll for events from the server
     *
     * @param string $endpoint The poll endpoint
     * @param RunConfig $config Run configuration
     * @param string|null &$lastEventId Reference to last event ID
     * @return void
     */
    private function pollForEvents(string $endpoint, RunConfig $config, ?string &$lastEventId): void
    {
        try {
            $request = $this->requestFactory->createRequest('GET', $endpoint);
            $request = $this->addHeaders($request);
            $request = $this->addRunConfigHeaders($request, $config);

            if ($lastEventId) {
                $request = $request->withHeader('Last-Event-ID', $lastEventId);
            }

            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() === 204) {
                // No new events
                return;
            }

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Poll request failed', [
                    'status' => $response->getStatusCode(),
                    'endpoint' => $endpoint
                ]);
                return;
            }

            $body = $response->getBody()->getContents();
            $this->processBinaryResponse($body, $lastEventId);

        } catch (\Throwable $error) {
            $this->logger->warning('Poll request error', [
                'error' => $error->getMessage(),
                'endpoint' => $endpoint
            ]);
        }
    }

    /**
     * Process binary response from server
     *
     * @param string $data The binary data
     * @param string|null &$lastEventId Reference to last event ID
     * @return void
     */
    private function processBinaryResponse(string $data, ?string &$lastEventId): void
    {
        if (empty($data)) {
            return;
        }

        // Decompress if needed
        if ($this->config['compression'] === 'gzip' && function_exists('gzdecode')) {
            $decompressed = @gzdecode($data);
            if ($decompressed !== false) {
                $data = $decompressed;
            }
        }

        try {
            $encoder = EncoderFactory::create('protobuf');
            $events = $this->deserializeEventBatch($data, $encoder);

            foreach ($events as $event) {
                $this->observable->emitEvent($event);
                $lastEventId = $event->getId();

                $this->logger->debug('HTTP Binary event processed', [
                    'event_id' => $event->getId(),
                    'type' => $event->getType()
                ]);
            }

            if (count($events) > 0) {
                $this->logger->debug('Processed event batch', [
                    'count' => count($events),
                    'last_event_id' => $lastEventId
                ]);
            }

        } catch (\Throwable $error) {
            $this->logger->error('Failed to process binary response', [
                'error' => $error->getMessage(),
                'data_length' => strlen($data)
            ]);
        }
    }

    /**
     * Deserialize event batch from binary data
     *
     * @param string $data The binary data
     * @param mixed $encoder The encoder to use
     * @return array<BaseEvent> Array of deserialized events
     */
    private function deserializeEventBatch(string $data, $encoder): array
    {
        $events = [];
        $offset = 0;

        while ($offset < strlen($data)) {
            // Read event length (4 bytes, big-endian)
            if ($offset + 4 > strlen($data)) {
                break;
            }

            $eventLength = unpack('N', substr($data, $offset, 4))[1];
            $offset += 4;

            if ($offset + $eventLength > strlen($data)) {
                break;
            }

            // Extract event data
            $eventData = substr($data, $offset, $eventLength);
            $offset += $eventLength;

            try {
                $decoded = $encoder->decode($eventData);
                
                if ($decoded instanceof BaseEvent) {
                    $events[] = $decoded;
                } elseif (is_array($decoded)) {
                    $event = BaseEvent::fromArray($decoded);
                    $events[] = $event;
                }
            } catch (\Throwable $error) {
                $this->logger->warning('Failed to deserialize event', [
                    'error' => $error->getMessage(),
                    'event_length' => $eventLength
                ]);
            }
        }

        return $events;
    }

    /**
     * Add headers to request
     *
     * @param RequestInterface $request The request
     * @param string|null $contentType Optional content type override
     * @return RequestInterface
     */
    private function addHeaders($request, ?string $contentType = null): RequestInterface
    {
        foreach ($this->config['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($contentType) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        return $request;
    }

    /**
     * Add run configuration headers to request
     *
     * @param RequestInterface $request The request
     * @param RunConfig $config The run configuration
     * @return RequestInterface
     */
    private function addRunConfigHeaders($request, RunConfig $config): RequestInterface
    {
        $request = $request->withHeader('X-Run-ID', $config->getRunId());
        
        if ($config->getSessionId()) {
            $request = $request->withHeader('X-Session-ID', $config->getSessionId());
        }

        if ($config->getUserId()) {
            $request = $request->withHeader('X-User-ID', $config->getUserId());
        }

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Events\BaseEvent;
use AGUI\Encoder\EncoderFactory;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;

/**
 * Server-Sent Events (SSE) transport implementation
 *
 * @package AGUI\Client\Transport
 */
class SSETransport extends AbstractTransport
{
    private ?ResponseInterface $response = null;
    private ?EventObservable $observable = null;
    private $stream = null;

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'sse';
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $data): void
    {
        // SSE is typically read-only, but we can send via separate HTTP POST
        if (!$this->connected) {
            throw new \RuntimeException('Transport not connected');
        }

        $sendEndpoint = $this->config['send_endpoint'] ?? $this->endpoint;
        
        $request = $this->requestFactory->createRequest('POST', $sendEndpoint);
        $request = $request->withBody($this->streamFactory->createStream($data));
        $request = $this->addHeaders($request);

        try {
            $this->logger->debug('Sending data via SSE POST', [
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
            $this->logger->error('Failed to send data via SSE', [
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

        // Start streaming in a non-blocking way
        Loop::futureTick(function () use ($config) {
            $this->startStreaming($config);
        });

        return $this->observable;
    }

    /**
     * {@inheritdoc}
     */
    protected function doConnect(): void
    {
        // Connection will be established when streaming starts
    }

    /**
     * {@inheritdoc}
     */
    protected function doDisconnect(): void
    {
        if ($this->stream) {
            fclose($this->stream);
            $this->stream = null;
        }

        $this->response = null;
        
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
            'read_timeout' => 5,
            'buffer_size' => 8192,
            'retry_delay' => 1000,
            'max_retries' => 3,
            'headers' => [
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive'
            ]
        ];
    }

    /**
     * Start the SSE streaming process
     *
     * @param RunConfig $config Run configuration
     * @return void
     */
    private function startStreaming(RunConfig $config): void
    {
        try {
            $request = $this->requestFactory->createRequest('GET', $this->endpoint);
            $request = $this->addHeaders($request);
            $request = $this->addRunConfigHeaders($request, $config);

            $this->logger->info('Starting SSE stream', [
                'endpoint' => $this->endpoint,
                'run_id' => $config->getRunId()
            ]);

            // Use Guzzle streaming for real-time processing
            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(
                    sprintf('SSE stream failed with status %d', $response->getStatusCode())
                );
            }

            $this->response = $response;
            $body = $response->getBody();
            
            if ($body instanceof Stream) {
                $this->stream = $body->detach();
                $this->processStream();
            }

        } catch (\Throwable $error) {
            $this->logger->error('SSE streaming failed', [
                'error' => $error->getMessage()
            ]);
            
            if ($this->observable) {
                $this->observable->emitError($error);
            }
        }
    }

    /**
     * Process the SSE stream
     *
     * @return void
     */
    private function processStream(): void
    {
        if (!$this->stream || !$this->observable) {
            return;
        }

        // Set stream to non-blocking
        stream_set_blocking($this->stream, false);

        $buffer = '';
        $encoder = EncoderFactory::create('json');

        $timer = Loop::addPeriodicTimer(0.1, function () use (&$buffer, $encoder) {
            if (!$this->stream || !$this->observable || feof($this->stream)) {
                return;
            }

            $data = fread($this->stream, $this->config['buffer_size']);
            if ($data === false) {
                return;
            }

            $buffer .= $data;
            
            // Process complete SSE messages
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $message = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $this->processSSEMessage($message, $encoder);
            }
        });

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
     * Process a single SSE message
     *
     * @param string $message The SSE message
     * @param mixed $encoder The encoder to use for deserialization
     * @return void
     */
    private function processSSEMessage(string $message, $encoder): void
    {
        $lines = explode("\n", trim($message));
        $eventType = null;
        $eventData = null;
        $eventId = null;

        foreach ($lines as $line) {
            if (empty($line) || $line[0] === ':') {
                continue; // Skip empty lines and comments
            }

            if (strpos($line, ':') === false) {
                continue; // Invalid format
            }

            [$field, $value] = explode(':', $line, 2);
            $field = trim($field);
            $value = trim($value);

            switch ($field) {
                case 'event':
                    $eventType = $value;
                    break;
                case 'data':
                    $eventData = ($eventData === null) ? $value : $eventData . "\n" . $value;
                    break;
                case 'id':
                    $eventId = $value;
                    break;
            }
        }

        if ($eventData !== null) {
            try {
                // Decode the event data
                $decoded = $encoder->decode($eventData);
                
                // Create BaseEvent if it's a valid AG-UI event
                if (is_array($decoded) && isset($decoded['type'])) {
                    $event = BaseEvent::fromArray($decoded);
                    $this->observable->emitEvent($event);

                    $this->logger->debug('SSE event processed', [
                        'type' => $eventType,
                        'id' => $eventId,
                        'event_id' => $event->getId()
                    ]);
                }
            } catch (\Throwable $error) {
                $this->logger->warning('Failed to process SSE event', [
                    'error' => $error->getMessage(),
                    'data' => $eventData
                ]);
            }
        }
    }

    /**
     * Add headers to request
     *
     * @param RequestInterface $request The request
     * @return RequestInterface
     */
    private function addHeaders(RequestInterface $request): RequestInterface
    {
        foreach ($this->config['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
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
    private function addRunConfigHeaders(RequestInterface $request, RunConfig $config): RequestInterface
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

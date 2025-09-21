<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Events\BaseEvent;
use AGUI\Encoder\EncoderFactory;
use React\EventLoop\Loop;
use React\Socket\Connector;
use React\Stream\WritableResourceStream;

/**
 * WebSocket transport implementation
 *
 * @package AGUI\Client\Transport
 */
class WebSocketTransport extends AbstractTransport
{
    private ?EventObservable $observable = null;
    private $connection = null;
    private bool $handshakeComplete = false;

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'websocket';
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $data): void
    {
        if (!$this->connected || !$this->connection) {
            throw new \RuntimeException('WebSocket not connected');
        }

        if (!$this->handshakeComplete) {
            throw new \RuntimeException('WebSocket handshake not complete');
        }

        $frame = $this->createWebSocketFrame($data);
        
        $this->logger->debug('Sending WebSocket message', [
            'data_length' => strlen($data),
            'frame_length' => strlen($frame)
        ]);

        $this->connection->write($frame);
    }

    /**
     * {@inheritdoc}
     */
    public function stream(RunConfig $config): EventObservable
    {
        if (!$this->connected) {
            throw new \RuntimeException('WebSocket not connected');
        }

        $this->observable = new EventObservable();
        
        // Start connection process
        Loop::futureTick(function () use ($config) {
            $this->establishConnection($config);
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
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->handshakeComplete = false;

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
            'ping_interval' => 30,
            'max_frame_size' => 65536,
            'protocols' => ['ag-ui'],
            'headers' => []
        ];
    }

    /**
     * Establish WebSocket connection
     *
     * @param RunConfig $config Run configuration
     * @return void
     */
    private function establishConnection(RunConfig $config): void
    {
        $connector = new Connector(Loop::get());
        $url = $this->parseWebSocketUrl($this->endpoint);

        $this->logger->info('Connecting to WebSocket', [
            'endpoint' => $this->endpoint,
            'host' => $url['host'],
            'port' => $url['port']
        ]);

        $connector->connect($url['host'] . ':' . $url['port'])
            ->then(function ($connection) use ($config, $url) {
                $this->connection = $connection;
                $this->performHandshake($config, $url);
                $this->setupConnectionHandlers();
            })
            ->otherwise(function (\Exception $error) {
                $this->logger->error('WebSocket connection failed', [
                    'error' => $error->getMessage()
                ]);

                if ($this->observable) {
                    $this->observable->emitError($error);
                }
            });
    }

    /**
     * Perform WebSocket handshake
     *
     * @param RunConfig $config Run configuration
     * @param array $url Parsed URL components
     * @return void
     */
    private function performHandshake(RunConfig $config, array $url): void
    {
        $key = base64_encode(random_bytes(16));
        $headers = [
            'GET ' . ($url['path'] ?? '/') . ' HTTP/1.1',
            'Host: ' . $url['host'] . ':' . $url['port'],
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: ' . $key,
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Protocol: ' . implode(', ', $this->config['protocols']),
            'X-Run-ID: ' . $config->getRunId()
        ];

        if ($config->getSessionId()) {
            $headers[] = 'X-Session-ID: ' . $config->getSessionId();
        }

        if ($config->getUserId()) {
            $headers[] = 'X-User-ID: ' . $config->getUserId();
        }

        foreach ($this->config['headers'] as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $handshake = implode("\r\n", $headers) . "\r\n\r\n";

        $this->logger->debug('Sending WebSocket handshake', [
            'key' => $key
        ]);

        $this->connection->write($handshake);
    }

    /**
     * Setup connection event handlers
     *
     * @return void
     */
    private function setupConnectionHandlers(): void
    {
        $buffer = '';
        $headersParsed = false;

        $this->connection->on('data', function ($data) use (&$buffer, &$headersParsed) {
            $buffer .= $data;

            if (!$headersParsed) {
                if (strpos($buffer, "\r\n\r\n") !== false) {
                    $this->processHandshakeResponse($buffer);
                    $headersParsed = true;
                    $buffer = substr($buffer, strpos($buffer, "\r\n\r\n") + 4);
                }
            }

            if ($headersParsed && $this->handshakeComplete) {
                $this->processWebSocketFrames($buffer);
            }
        });

        $this->connection->on('close', function () {
            $this->logger->info('WebSocket connection closed');
            
            if ($this->observable) {
                $this->observable->complete();
            }
        });

        $this->connection->on('error', function (\Exception $error) {
            $this->logger->error('WebSocket connection error', [
                'error' => $error->getMessage()
            ]);

            if ($this->observable) {
                $this->observable->emitError($error);
            }
        });
    }

    /**
     * Process WebSocket handshake response
     *
     * @param string $response The handshake response
     * @return void
     */
    private function processHandshakeResponse(string $response): void
    {
        $lines = explode("\r\n", $response);
        $statusLine = array_shift($lines);

        if (!preg_match('/HTTP\/1\.1 101/', $statusLine)) {
            throw new \RuntimeException('WebSocket handshake failed: ' . $statusLine);
        }

        $this->handshakeComplete = true;
        $this->logger->info('WebSocket handshake complete');
    }

    /**
     * Process WebSocket frames
     *
     * @param string &$buffer The data buffer
     * @return void
     */
    private function processWebSocketFrames(string &$buffer): void
    {
        while (strlen($buffer) >= 2) {
            $frame = $this->parseWebSocketFrame($buffer);
            
            if ($frame === null) {
                break; // Incomplete frame
            }

            $this->handleWebSocketFrame($frame);
        }
    }

    /**
     * Parse a WebSocket frame from buffer
     *
     * @param string &$buffer The data buffer
     * @return array|null Parsed frame or null if incomplete
     */
    private function parseWebSocketFrame(string &$buffer): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        $firstByte = ord($buffer[0]);
        $secondByte = ord($buffer[1]);

        $fin = ($firstByte & 0x80) === 0x80;
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7F;

        $offset = 2;

        if ($payloadLength === 126) {
            if (strlen($buffer) < $offset + 2) {
                return null;
            }
            $payloadLength = unpack('n', substr($buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if (strlen($buffer) < $offset + 8) {
                return null;
            }
            $payloadLength = unpack('J', substr($buffer, $offset, 8))[1];
            $offset += 8;
        }

        $maskKey = null;
        if ($masked) {
            if (strlen($buffer) < $offset + 4) {
                return null;
            }
            $maskKey = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if (strlen($buffer) < $offset + $payloadLength) {
            return null;
        }

        $payload = substr($buffer, $offset, $payloadLength);
        $buffer = substr($buffer, $offset + $payloadLength);

        if ($masked && $maskKey) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
            }
        }

        return [
            'fin' => $fin,
            'opcode' => $opcode,
            'payload' => $payload
        ];
    }

    /**
     * Handle a parsed WebSocket frame
     *
     * @param array $frame The parsed frame
     * @return void
     */
    private function handleWebSocketFrame(array $frame): void
    {
        switch ($frame['opcode']) {
            case 0x1: // Text frame
                $this->handleTextFrame($frame['payload']);
                break;
            case 0x2: // Binary frame
                $this->handleBinaryFrame($frame['payload']);
                break;
            case 0x8: // Close frame
                $this->connection->close();
                break;
            case 0x9: // Ping frame
                $this->sendPong($frame['payload']);
                break;
            case 0xA: // Pong frame
                $this->logger->debug('Received pong frame');
                break;
        }
    }

    /**
     * Handle text frame
     *
     * @param string $payload The frame payload
     * @return void
     */
    private function handleTextFrame(string $payload): void
    {
        try {
            $encoder = EncoderFactory::create('json');
            $decoded = $encoder->decode($payload);

            if (is_array($decoded) && isset($decoded['type'])) {
                $event = BaseEvent::fromArray($decoded);
                $this->observable->emitEvent($event);

                $this->logger->debug('WebSocket event processed', [
                    'event_id' => $event->getId(),
                    'type' => $event->getType()
                ]);
            }
        } catch (\Throwable $error) {
            $this->logger->warning('Failed to process WebSocket text frame', [
                'error' => $error->getMessage(),
                'payload' => $payload
            ]);
        }
    }

    /**
     * Handle binary frame
     *
     * @param string $payload The frame payload
     * @return void
     */
    private function handleBinaryFrame(string $payload): void
    {
        try {
            $encoder = EncoderFactory::create('protobuf');
            $decoded = $encoder->decode($payload);

            // Process decoded protobuf data
            if ($decoded instanceof BaseEvent) {
                $this->observable->emitEvent($decoded);
            }
        } catch (\Throwable $error) {
            $this->logger->warning('Failed to process WebSocket binary frame', [
                'error' => $error->getMessage(),
                'payload_length' => strlen($payload)
            ]);
        }
    }

    /**
     * Send pong frame
     *
     * @param string $payload The payload to echo back
     * @return void
     */
    private function sendPong(string $payload): void
    {
        $frame = $this->createWebSocketFrame($payload, 0xA);
        $this->connection->write($frame);
    }

    /**
     * Create WebSocket frame
     *
     * @param string $data The data to frame
     * @param int $opcode The frame opcode (default: 0x1 for text)
     * @return string The framed data
     */
    private function createWebSocketFrame(string $data, int $opcode = 0x1): string
    {
        $length = strlen($data);
        $firstByte = 0x80 | $opcode; // FIN bit set

        if ($length < 126) {
            $header = pack('CC', $firstByte, $length | 0x80); // Set mask bit
        } elseif ($length < 65536) {
            $header = pack('CCn', $firstByte, 126 | 0x80, $length);
        } else {
            $header = pack('CCJ', $firstByte, 127 | 0x80, $length);
        }

        // Generate mask key
        $maskKey = random_bytes(4);
        $header .= $maskKey;

        // Mask the data
        for ($i = 0; $i < $length; $i++) {
            $data[$i] = chr(ord($data[$i]) ^ ord($maskKey[$i % 4]));
        }

        return $header . $data;
    }

    /**
     * Parse WebSocket URL
     *
     * @param string $url The WebSocket URL
     * @return array Parsed URL components
     */
    private function parseWebSocketUrl(string $url): array
    {
        $parsed = parse_url($url);
        
        if (!$parsed) {
            throw new \InvalidArgumentException('Invalid WebSocket URL: ' . $url);
        }

        $scheme = $parsed['scheme'] ?? 'ws';
        $defaultPort = ($scheme === 'wss') ? 443 : 80;

        return [
            'scheme' => $scheme,
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? $defaultPort,
            'path' => $parsed['path'] ?? '/',
            'query' => $parsed['query'] ?? ''
        ];
    }
}

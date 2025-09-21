<?php

declare(strict_types=1);

namespace AGUI\Client\Transport;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating transport instances
 *
 * @package AGUI\Client\Transport
 */
class TransportFactory
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private array $transportClasses;

    /**
     * TransportFactory constructor
     *
     * @param ClientInterface $httpClient HTTP client implementation
     * @param RequestFactoryInterface $requestFactory Request factory implementation
     * @param StreamFactoryInterface $streamFactory Stream factory implementation
     * @param LoggerInterface|null $logger Logger implementation
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger ?? new NullLogger();
        
        $this->transportClasses = $this->getDefaultTransportClasses();
    }

    /**
     * Create a transport instance
     *
     * @param string $type Transport type
     * @param array<string, mixed> $config Transport configuration
     * @return TransportInterface
     * @throws \InvalidArgumentException If transport type is not supported
     */
    public function create(string $type, array $config = []): TransportInterface
    {
        if (!isset($this->transportClasses[$type])) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported transport type: %s. Available types: %s', 
                    $type, 
                    implode(', ', array_keys($this->transportClasses))
                )
            );
        }

        $className = $this->transportClasses[$type];

        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                sprintf('Transport class not found: %s', $className)
            );
        }

        $this->logger->debug('Creating transport', [
            'type' => $type,
            'class' => $className,
            'config' => $config
        ]);

        $transport = new $className(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $config,
            $this->logger
        );

        if (!$transport instanceof TransportInterface) {
            throw new \RuntimeException(
                sprintf('Transport class %s does not implement TransportInterface', $className)
            );
        }

        return $transport;
    }

    /**
     * Register a custom transport class
     *
     * @param string $type Transport type identifier
     * @param string $className Fully qualified class name
     * @return $this
     * @throws \InvalidArgumentException If class doesn't exist or implement interface
     */
    public function registerTransport(string $type, string $className): self
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                sprintf('Transport class not found: %s', $className)
            );
        }

        $reflection = new \ReflectionClass($className);
        if (!$reflection->implementsInterface(TransportInterface::class)) {
            throw new \InvalidArgumentException(
                sprintf('Transport class %s must implement TransportInterface', $className)
            );
        }

        $this->transportClasses[$type] = $className;

        $this->logger->debug('Registered custom transport', [
            'type' => $type,
            'class' => $className
        ]);

        return $this;
    }

    /**
     * Unregister a transport type
     *
     * @param string $type Transport type to unregister
     * @return $this
     */
    public function unregisterTransport(string $type): self
    {
        unset($this->transportClasses[$type]);

        $this->logger->debug('Unregistered transport', [
            'type' => $type
        ]);

        return $this;
    }

    /**
     * Get all supported transport types
     *
     * @return array<string> Array of supported transport types
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->transportClasses);
    }

    /**
     * Check if a transport type is supported
     *
     * @param string $type Transport type to check
     * @return bool True if supported, false otherwise
     */
    public function isSupported(string $type): bool
    {
        return isset($this->transportClasses[$type]);
    }

    /**
     * Get transport class for a given type
     *
     * @param string $type Transport type
     * @return string|null Transport class name or null if not found
     */
    public function getTransportClass(string $type): ?string
    {
        return $this->transportClasses[$type] ?? null;
    }

    /**
     * Create multiple transports
     *
     * @param array<string, array> $configs Array of transport configs keyed by type
     * @return array<string, TransportInterface> Array of transport instances keyed by type
     */
    public function createMultiple(array $configs): array
    {
        $transports = [];

        foreach ($configs as $type => $config) {
            $transports[$type] = $this->create($type, $config);
        }

        return $transports;
    }

    /**
     * Create transport with fallback types
     *
     * @param array<string> $types Array of transport types in priority order
     * @param array<string, mixed> $config Transport configuration
     * @return TransportInterface
     * @throws \RuntimeException If no fallback transports are available
     */
    public function createWithFallback(array $types, array $config = []): TransportInterface
    {
        $errors = [];

        foreach ($types as $type) {
            try {
                return $this->create($type, $config);
            } catch (\Throwable $error) {
                $errors[$type] = $error->getMessage();
                $this->logger->debug('Transport creation failed, trying fallback', [
                    'type' => $type,
                    'error' => $error->getMessage()
                ]);
            }
        }

        throw new \RuntimeException(
            sprintf('Failed to create transport with any fallback type. Errors: %s',
                json_encode($errors)
            )
        );
    }

    /**
     * Get recommended transport type based on URL scheme
     *
     * @param string $url The endpoint URL
     * @return string Recommended transport type
     */
    public function getRecommendedType(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        switch (strtolower($scheme)) {
            case 'ws':
            case 'wss':
                return 'websocket';
            case 'http':
            case 'https':
                // Check URL path for hints
                if (strpos($url, '/sse') !== false || strpos($url, '/events') !== false) {
                    return 'sse';
                } elseif (strpos($url, '/binary') !== false) {
                    return 'http-binary';
                }
                return 'sse'; // Default for HTTP
            default:
                return 'sse'; // Safe default
        }
    }

    /**
     * Get default transport classes
     *
     * @return array<string, string> Array mapping transport types to class names
     */
    private function getDefaultTransportClasses(): array
    {
        return [
            'sse' => SSETransport::class,
            'websocket' => WebSocketTransport::class,
            'http-binary' => HttpBinaryTransport::class,
        ];
    }
}

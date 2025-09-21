<?php

declare(strict_types=1);

namespace AGUI\Core\Types;

use AGUI\Core\Validation\ValidationException;
use Respect\Validation\Validator as v;
use Ramsey\Uuid\Uuid;

/**
 * Configuration for agent run execution
 *
 * @package AGUI\Core\Types
 */
final class RunConfig
{
    /**
     * @param string $runId The unique run identifier
     * @param string $endpoint The server endpoint URL
     * @param string|null $sessionId Optional session identifier
     * @param string|null $userId Optional user identifier
     * @param string|null $transportType Transport type (sse, websocket, http-binary)
     * @param array<string, mixed> $transportOptions Transport-specific options
     * @param array<string, mixed> $headers Additional HTTP headers
     * @param int $timeout Request timeout in seconds
     * @param array<string, mixed> $metadata Additional metadata
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $endpoint,
        public readonly ?string $sessionId = null,
        public readonly ?string $userId = null,
        public readonly ?string $transportType = null,
        public readonly array $transportOptions = [],
        public readonly array $headers = [],
        public readonly int $timeout = 30,
        public readonly array $metadata = []
    ) {
        $this->validate();
    }

    /**
     * Validate the run configuration properties
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = v::key('runId', v::stringType()->notEmpty())
            ->key('endpoint', v::url())
            ->key('timeout', v::intVal()->min(1)->max(300))
            ->key('transportOptions', v::arrayType())
            ->key('headers', v::arrayType())
            ->key('metadata', v::arrayType());

        $data = [
            'runId' => $this->runId,
            'endpoint' => $this->endpoint,
            'timeout' => $this->timeout,
            'transportOptions' => $this->transportOptions,
            'headers' => $this->headers,
            'metadata' => $this->metadata
        ];

        if (!$validator->validate($data)) {
            throw new ValidationException('Invalid run configuration data');
        }

        if ($this->sessionId !== null && !v::stringType()->notEmpty()->validate($this->sessionId)) {
            throw new ValidationException('Session ID must be a non-empty string when provided');
        }

        if ($this->userId !== null && !v::stringType()->notEmpty()->validate($this->userId)) {
            throw new ValidationException('User ID must be a non-empty string when provided');
        }

        if ($this->transportType !== null) {
            $validTransports = ['sse', 'websocket', 'http-binary'];
            if (!in_array($this->transportType, $validTransports)) {
                throw new ValidationException(
                    sprintf('Transport type must be one of: %s', implode(', ', $validTransports))
                );
            }
        }
    }

    /**
     * Create a RunConfig from an array
     *
     * @param array{
     *     runId: string,
     *     endpoint: string,
     *     sessionId?: string|null,
     *     userId?: string|null,
     *     transportType?: string|null,
     *     transportOptions?: array<string, mixed>,
     *     headers?: array<string, mixed>,
     *     timeout?: int,
     *     metadata?: array<string, mixed>
     * } $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['runId'],
            $data['endpoint'],
            $data['sessionId'] ?? null,
            $data['userId'] ?? null,
            $data['transportType'] ?? null,
            $data['transportOptions'] ?? [],
            $data['headers'] ?? [],
            $data['timeout'] ?? 30,
            $data['metadata'] ?? []
        );
    }

    /**
     * Create a RunConfig with generated run ID
     *
     * @param string $endpoint The server endpoint URL
     * @param array<string, mixed> $options Additional options
     * @return static
     */
    public static function create(string $endpoint, array $options = []): static
    {
        $defaults = [
            'runId' => Uuid::uuid4()->toString(),
            'sessionId' => null,
            'userId' => null,
            'transportType' => null,
            'transportOptions' => [],
            'headers' => [],
            'timeout' => 30,
            'metadata' => []
        ];

        $config = array_merge($defaults, $options, ['endpoint' => $endpoint]);
        
        return self::fromArray($config);
    }

    /**
     * Create a copy with modified properties
     *
     * @param array<string, mixed> $changes Properties to change
     * @return static
     */
    public function with(array $changes): static
    {
        $data = $this->toArray();
        $data = array_merge($data, $changes);
        
        return self::fromArray($data);
    }

    /**
     * Convert to array representation
     *
     * @return array{
     *     runId: string,
     *     endpoint: string,
     *     sessionId: string|null,
     *     userId: string|null,
     *     transportType: string|null,
     *     transportOptions: array<string, mixed>,
     *     headers: array<string, mixed>,
     *     timeout: int,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'runId' => $this->runId,
            'endpoint' => $this->endpoint,
            'sessionId' => $this->sessionId,
            'userId' => $this->userId,
            'transportType' => $this->transportType,
            'transportOptions' => $this->transportOptions,
            'headers' => $this->headers,
            'timeout' => $this->timeout,
            'metadata' => $this->metadata
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

    /**
     * Get run identifier
     *
     * @return string
     */
    public function getRunId(): string
    {
        return $this->runId;
    }

    /**
     * Get endpoint URL
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get session identifier
     *
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get user identifier
     *
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Get transport type
     *
     * @return string|null
     */
    public function getTransportType(): ?string
    {
        return $this->transportType;
    }

    /**
     * Get transport options
     *
     * @return array<string, mixed>
     */
    public function getTransportOptions(): array
    {
        return $this->transportOptions;
    }

    /**
     * Get HTTP headers
     *
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get timeout in seconds
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if a specific transport option exists
     *
     * @param string $key Option key
     * @return bool
     */
    public function hasTransportOption(string $key): bool
    {
        return array_key_exists($key, $this->transportOptions);
    }

    /**
     * Get a specific transport option value
     *
     * @param string $key Option key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getTransportOption(string $key, mixed $default = null): mixed
    {
        return $this->transportOptions[$key] ?? $default;
    }

    /**
     * Check if a specific header exists
     *
     * @param string $name Header name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    /**
     * Get a specific header value
     *
     * @param string $name Header name
     * @param mixed $default Default value if header doesn't exist
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Check if a specific metadata key exists
     *
     * @param string $key Metadata key
     * @return bool
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Get a specific metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}

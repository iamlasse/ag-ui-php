<?php

declare(strict_types=1);

namespace AGUI\Encoder;

/**
 * Protocol Buffers encoder implementation
 *
 * @package AGUI\Encoder
 */
class ProtobufEncoder implements EncoderInterface
{
    private array $config;

    /**
     * ProtobufEncoder constructor
     *
     * @param array<string, mixed> $config Encoder configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(mixed $data): string
    {
        // For now, implement basic binary encoding
        // In a real implementation, this would use protobuf libraries
        
        if (is_object($data) && method_exists($data, 'serializeToString')) {
            return $data->serializeToString();
        }
        
        if (is_array($data) || is_object($data)) {
            // Fallback to JSON for compatibility
            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        throw new EncodingException('Unable to encode data as protobuf: unsupported data type');
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $data): mixed
    {
        // For now, implement basic binary decoding
        // In a real implementation, this would use protobuf libraries
        
        // Try to decode as JSON first (fallback compatibility)
        if ($this->isValidJson($data)) {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        // For binary protobuf data, we would need specific message classes
        // This is a placeholder implementation
        throw new DecodingException('Protobuf decoding not fully implemented - binary data requires specific message classes');
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'protobuf';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if string is valid JSON
     *
     * @param string $data Data to check
     * @return bool
     */
    private function isValidJson(string $data): bool
    {
        json_decode($data);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get default configuration
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'fallback_to_json' => true,
            'strict_mode' => false
        ];
    }
}

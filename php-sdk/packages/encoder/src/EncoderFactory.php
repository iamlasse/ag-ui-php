<?php

declare(strict_types=1);

namespace AGUI\Encoder;

/**
 * Factory for creating encoder instances
 *
 * @package AGUI\Encoder
 */
class EncoderFactory
{
    /**
     * Create an encoder instance
     *
     * @param string $type Encoder type (json, protobuf)
     * @param array<string, mixed> $options Encoder options
     * @return EncoderInterface
     * @throws \InvalidArgumentException If encoder type is not supported
     */
    public static function create(string $type, array $options = []): EncoderInterface
    {
        switch (strtolower($type)) {
            case 'json':
                return new JsonEncoder($options);
            case 'protobuf':
                return new ProtobufEncoder($options);
            default:
                throw new \InvalidArgumentException("Unsupported encoder type: {$type}");
        }
    }

    /**
     * Get all supported encoder types
     *
     * @return array<string>
     */
    public static function getSupportedTypes(): array
    {
        return ['json', 'protobuf'];
    }

    /**
     * Check if an encoder type is supported
     *
     * @param string $type Encoder type to check
     * @return bool
     */
    public static function isSupported(string $type): bool
    {
        return in_array(strtolower($type), self::getSupportedTypes());
    }
}

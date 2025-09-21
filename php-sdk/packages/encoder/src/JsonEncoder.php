<?php

declare(strict_types=1);

namespace AGUI\Encoder;

/**
 * JSON encoder implementation
 *
 * @package AGUI\Encoder
 */
class JsonEncoder implements EncoderInterface
{
    private array $config;

    /**
     * JsonEncoder constructor
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
        $flags = $this->config['flags'];
        $depth = $this->config['depth'];

        try {
            $result = json_encode($data, $flags, $depth);
            
            if ($result === false) {
                throw new EncodingException('JSON encoding failed: ' . json_last_error_msg());
            }

            return $result;
        } catch (\JsonException $e) {
            throw new EncodingException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $data): mixed
    {
        if (empty($data)) {
            throw new DecodingException('Cannot decode empty JSON string');
        }

        $associative = $this->config['associative'];
        $depth = $this->config['depth'];
        $flags = $this->config['decode_flags'];

        try {
            $result = json_decode($data, $associative, $depth, $flags);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new DecodingException('JSON decoding failed: ' . json_last_error_msg());
            }

            return $result;
        } catch (\JsonException $e) {
            throw new DecodingException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get default configuration
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'flags' => JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            'decode_flags' => JSON_THROW_ON_ERROR,
            'depth' => 512,
            'associative' => true
        ];
    }
}

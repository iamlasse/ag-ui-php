<?php

declare(strict_types=1);

namespace AGUI\Encoder;

/**
 * Interface for data encoders/decoders
 *
 * @package AGUI\Encoder
 */
interface EncoderInterface
{
    /**
     * Encode data to string
     *
     * @param mixed $data Data to encode
     * @return string Encoded data
     * @throws EncodingException If encoding fails
     */
    public function encode(mixed $data): string;

    /**
     * Decode string to data
     *
     * @param string $data Encoded data string
     * @return mixed Decoded data
     * @throws DecodingException If decoding fails
     */
    public function decode(string $data): mixed;

    /**
     * Get the encoder type
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get encoder configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;
}

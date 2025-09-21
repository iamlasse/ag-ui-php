<?php

declare(strict_types=1);

namespace AGUI\Proto;

/**
 * AG-UI Protocol Buffer facade
 * 
 * This class provides a simple API compatible with the TypeScript implementation
 * for encoding and decoding AG-UI protocol buffer events.
 */
class Proto
{
    public const AGUI_MEDIA_TYPE = ProtoEncoder::AGUI_MEDIA_TYPE;

    private static ?ProtoEncoder $encoder = null;

    /**
     * Get the encoder instance (singleton).
     */
    private static function getEncoder(): ProtoEncoder
    {
        if (self::$encoder === null) {
            self::$encoder = new ProtoEncoder();
        }
        
        return self::$encoder;
    }

    /**
     * Encodes an event array to protocol buffer binary format.
     *
     * @param array $event Event data array
     * @return string Binary protocol buffer data
     */
    public static function encode(array $event): string
    {
        return self::getEncoder()->encode($event);
    }

    /**
     * Decodes protocol buffer binary data to an event array.
     *
     * @param string $data Binary protocol buffer data
     * @return array Decoded event data
     */
    public static function decode(string $data): array
    {
        return self::getEncoder()->decode($data);
    }
}

<?php

declare(strict_types=1);

namespace AGUI\Encoder;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Validation\ValidationException;
use JsonException;
use InvalidArgumentException;

/**
 * EventEncoder provides encoding and decoding capabilities for AG-UI events
 * with JSON serialization and type discrimination
 *
 * @package AGUI\Encoder
 */
final class EventEncoder
{
    /**
     * Schema version for backwards compatibility
     */
    private const SCHEMA_VERSION = '1.0';

    /**
     * Maximum allowed JSON depth to prevent deep recursion attacks
     */
    private const MAX_JSON_DEPTH = 512;

    /**
     * JSON encoding flags for consistent output
     */
    private const JSON_ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * JSON decoding flags for consistent parsing
     */
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR;

    private bool $validateSchema;
    private bool $includeTimestamp;

    /**
     * @param bool $validateSchema Whether to validate events against schema
     * @param bool $includeTimestamp Whether to automatically include timestamps
     */
    public function __construct(
        bool $validateSchema = true,
        bool $includeTimestamp = true
    ) {
        $this->validateSchema = $validateSchema;
        $this->includeTimestamp = $includeTimestamp;
    }

    /**
     * Encode an event to JSON string
     *
     * @param BaseEvent $event The event to encode
     * @param array<string, mixed>|null $context Optional encoding context
     * @return string JSON encoded event
     * @throws EncodingException If encoding fails
     */
    public function encode(BaseEvent $event, ?array $context = null): string
    {
        try {
            $eventData = $this->prepareEventData($event, $context);
            
            if ($this->validateSchema) {
                $this->validateEventData($eventData);
            }

            return json_encode($eventData, self::JSON_ENCODE_FLAGS, self::MAX_JSON_DEPTH);
        } catch (JsonException $e) {
            throw new EncodingException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        } catch (ValidationException $e) {
            throw new EncodingException('Event validation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Encode multiple events to JSON string
     *
     * @param array<BaseEvent> $events The events to encode
     * @param array<string, mixed>|null $context Optional encoding context
     * @return string JSON encoded events array
     * @throws EncodingException If encoding fails
     */
    public function encodeMultiple(array $events, ?array $context = null): string
    {
        try {
            $encodedEvents = [];
            
            foreach ($events as $index => $event) {
                if (!$event instanceof BaseEvent) {
                    throw new InvalidArgumentException("Event at index {$index} is not a BaseEvent instance");
                }
                
                $encodedEvents[] = $this->prepareEventData($event, $context);
            }

            $wrappedData = [
                'schema_version' => self::SCHEMA_VERSION,
                'events' => $encodedEvents,
                'encoded_at' => time(),
            ];

            return json_encode($wrappedData, self::JSON_ENCODE_FLAGS, self::MAX_JSON_DEPTH);
        } catch (JsonException $e) {
            throw new EncodingException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode JSON string to event object
     *
     * @param string $json JSON encoded event
     * @param array<string, mixed>|null $context Optional decoding context
     * @return BaseEvent Decoded event object
     * @throws DecodingException If decoding fails
     */
    public function decode(string $json, ?array $context = null): BaseEvent
    {
        try {
            $data = json_decode($json, true, self::MAX_JSON_DEPTH, self::JSON_DECODE_FLAGS);
            
            if (!is_array($data)) {
                throw new DecodingException('Invalid JSON: expected object, got ' . gettype($data));
            }

            return $this->createEventFromData($data, $context);
        } catch (JsonException $e) {
            throw new DecodingException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        } catch (ValidationException $e) {
            throw new DecodingException('Event creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode JSON string to multiple event objects
     *
     * @param string $json JSON encoded events array
     * @param array<string, mixed>|null $context Optional decoding context
     * @return array<BaseEvent> Array of decoded event objects
     * @throws DecodingException If decoding fails
     */
    public function decodeMultiple(string $json, ?array $context = null): array
    {
        try {
            $data = json_decode($json, true, self::MAX_JSON_DEPTH, self::JSON_DECODE_FLAGS);
            
            if (!is_array($data)) {
                throw new DecodingException('Invalid JSON: expected object, got ' . gettype($data));
            }

            // Handle wrapped format (with schema_version) or direct events array
            $eventsData = $data['events'] ?? $data;
            
            if (!is_array($eventsData)) {
                throw new DecodingException('Invalid format: events must be an array');
            }

            $events = [];
            foreach ($eventsData as $index => $eventData) {
                if (!is_array($eventData)) {
                    throw new DecodingException("Invalid event at index {$index}: expected object, got " . gettype($eventData));
                }
                
                $events[] = $this->createEventFromData($eventData, $context);
            }

            return $events;
        } catch (JsonException $e) {
            throw new DecodingException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        } catch (ValidationException $e) {
            throw new DecodingException('Event creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the type of an encoded event without full decoding
     *
     * @param string $json JSON encoded event
     * @return string Event type
     * @throws DecodingException If type detection fails
     */
    public function getEventType(string $json): string
    {
        try {
            $data = json_decode($json, true, self::MAX_JSON_DEPTH, self::JSON_DECODE_FLAGS);
            
            if (!is_array($data) || !isset($data['type']) || !is_string($data['type'])) {
                throw new DecodingException('Invalid event format: missing or invalid type field');
            }

            return $data['type'];
        } catch (JsonException $e) {
            throw new DecodingException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate if JSON string represents a valid event
     *
     * @param string $json JSON string to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidEvent(string $json): bool
    {
        try {
            $this->decode($json);
            return true;
        } catch (DecodingException) {
            return false;
        }
    }

    /**
     * Create event from array data using type discrimination
     *
     * @param array<string, mixed> $data Event data
     * @param array<string, mixed>|null $context Optional context
     * @return BaseEvent Created event
     * @throws ValidationException If creation fails
     */
    private function createEventFromData(array $data, ?array $context = null): BaseEvent
    {
        if (!isset($data['type'])) {
            throw new ValidationException('Event data missing required "type" field');
        }

        $eventType = $data['type'];
        if (!EventType::isValid($eventType)) {
            throw new ValidationException('Invalid event type: ' . $eventType);
        }

        // Use EventFactory for type-safe creation
        return EventFactory::fromArray($data);
    }

    /**
     * Prepare event data for encoding
     *
     * @param BaseEvent $event Event to prepare
     * @param array<string, mixed>|null $context Optional context
     * @return array<string, mixed> Prepared event data
     */
    private function prepareEventData(BaseEvent $event, ?array $context = null): array
    {
        $eventData = $event->getFullData();
        
        // Add schema version for compatibility
        $eventData['schema_version'] = self::SCHEMA_VERSION;
        
        // Add encoding timestamp if configured
        if ($this->includeTimestamp && !isset($eventData['timestamp'])) {
            $eventData['timestamp'] = time();
        }
        
        // Apply context if provided
        if ($context !== null) {
            $eventData['context'] = $context;
        }

        return $eventData;
    }

    /**
     * Validate event data against schema requirements
     *
     * @param array<string, mixed> $eventData Event data to validate
     * @throws ValidationException If validation fails
     */
    private function validateEventData(array $eventData): void
    {
        $requiredFields = ['id', 'type', 'schema_version'];
        
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $eventData)) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }

        if (!EventType::isValid($eventData['type'])) {
            throw new ValidationException('Invalid event type: ' . $eventData['type']);
        }

        if ($eventData['schema_version'] !== self::SCHEMA_VERSION) {
            throw new ValidationException('Schema version mismatch: expected ' . self::SCHEMA_VERSION . ', got ' . $eventData['schema_version']);
        }
    }

    /**
     * Get current schema version
     *
     * @return string Schema version
     */
    public function getSchemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Check if schema validation is enabled
     *
     * @return bool True if validation enabled
     */
    public function isSchemaValidationEnabled(): bool
    {
        return $this->validateSchema;
    }

    /**
     * Enable or disable schema validation
     *
     * @param bool $enabled Whether to enable validation
     * @return self For method chaining
     */
    public function setSchemaValidation(bool $enabled): self
    {
        $this->validateSchema = $enabled;
        return $this;
    }

    /**
     * Check if automatic timestamps are enabled
     *
     * @return bool True if timestamps enabled
     */
    public function isTimestampEnabled(): bool
    {
        return $this->includeTimestamp;
    }

    /**
     * Enable or disable automatic timestamps
     *
     * @param bool $enabled Whether to enable timestamps
     * @return self For method chaining
     */
    public function setTimestampEnabled(bool $enabled): self
    {
        $this->includeTimestamp = $enabled;
        return $this;
    }
}

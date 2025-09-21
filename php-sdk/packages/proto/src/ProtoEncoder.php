<?php

declare(strict_types=1);

namespace AGUI\Proto;

use Ag_ui\Event;
use Ag_ui\EventType;
use Ag_ui\JsonPatchOperationType;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Value;
use InvalidArgumentException;
use RuntimeException;

/**
 * PHP Protocol Buffer encoder/decoder for AG-UI events
 * 
 * This class provides encoding and decoding functionality for AG-UI events
 * maintaining API compatibility with the TypeScript implementation.
 */
class ProtoEncoder
{
    public const AGUI_MEDIA_TYPE = 'application/vnd.ag-ui.event+proto';

    /**
     * Encodes an event array to protocol buffer binary format.
     *
     * @param array $event Event data array
     * @return string Binary protocol buffer data
     * @throws InvalidArgumentException If event type is invalid
     * @throws RuntimeException If encoding fails
     */
    public function encode(array $event): string
    {
        if (!isset($event['type'])) {
            throw new InvalidArgumentException('Event must have a type field');
        }

        $oneofField = $this->toCamelCase($event['type']);
        $eventType = $this->getEventTypeConstant($event['type']);
        
        // Extract base event fields
        $baseEventData = [
            'type' => $eventType,
            'timestamp' => $event['timestamp'] ?? null,
            'raw_event' => isset($event['rawEvent']) ? $this->convertToValue($event['rawEvent']) : null,
        ];

        // Remove base fields from event data
        $eventData = $event;
        unset($eventData['type'], $eventData['timestamp'], $eventData['rawEvent']);

        // Handle specific event type transformations
        $eventData = $this->transformEventData($event['type'], $eventData);

        // Create the specific event message class
        $eventClassName = 'Ag_ui\\' . $this->pascalCase($oneofField) . 'Event';
        
        if (!class_exists($eventClassName)) {
            throw new InvalidArgumentException("Unknown event type: {$event['type']}");
        }

        $eventMessage = new $eventClassName([
            'base_event' => $baseEventData,
            ...$eventData
        ]);

        // Create the main Event wrapper
        $mainEvent = new Event([
            $oneofField => $eventMessage
        ]);

        return $mainEvent->serializeToString();
    }

    /**
     * Decodes protocol buffer binary data to an event array.
     *
     * @param string $data Binary protocol buffer data
     * @return array Decoded event data
     * @throws RuntimeException If decoding fails
     */
    public function decode(string $data): array
    {
        $event = new Event();
        
        try {
            $event->mergeFromString($data);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to decode protobuf data: ' . $e->getMessage(), 0, $e);
        }

        // Find the populated oneof field
        $eventMessage = null;
        $eventType = null;

        // Use reflection to get all possible event types
        $reflection = new \ReflectionClass($event);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'get') === 0 && $method->getName() !== 'getDescriptor') {
                $getter = $method->getName();
                $value = $event->$getter();
                
                if ($value !== null) {
                    $eventMessage = $value;
                    $eventType = $this->fromCamelCase(substr($getter, 3));
                    break;
                }
            }
        }

        if ($eventMessage === null) {
            throw new RuntimeException('No event data found in decoded message');
        }

        $baseEvent = $eventMessage->getBaseEvent();
        
        $result = [
            'type' => $eventType,
            'timestamp' => $baseEvent->getTimestamp(),
            'rawEvent' => $this->convertFromValue($baseEvent->getRawEvent()),
        ];

        // Extract event-specific data
        $eventData = $this->extractEventData($eventMessage);
        $result = array_merge($result, $eventData);

        // Clean up null values
        return array_filter($result, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Converts a snake_case string to camelCase.
     */
    private function toCamelCase(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * Converts a camelCase string to snake_case.
     */
    private function fromCamelCase(string $str): string
    {
        return strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }

    /**
     * Converts a string to PascalCase.
     */
    private function pascalCase(string $str): string
    {
        return ucfirst($this->toCamelCase($str));
    }

    /**
     * Gets the EventType constant for a given event type string.
     */
    private function getEventTypeConstant(string $eventType): int
    {
        $constant = 'Ag_ui\\EventType::' . $eventType;
        
        if (!defined($constant)) {
            throw new InvalidArgumentException("Unknown event type: $eventType");
        }
        
        return constant($constant);
    }

    /**
     * Transforms event data for specific event types.
     */
    private function transformEventData(string $eventType, array $eventData): array
    {
        switch ($eventType) {
            case 'MESSAGES_SNAPSHOT':
                // Ensure toolCalls array is always present for messages
                if (isset($eventData['messages'])) {
                    foreach ($eventData['messages'] as &$message) {
                        if (!isset($message['toolCalls'])) {
                            $message['toolCalls'] = [];
                        }
                    }
                }
                break;

            case 'STATE_DELTA':
                // Transform JSON patch operations
                if (isset($eventData['delta'])) {
                    foreach ($eventData['delta'] as &$operation) {
                        if (isset($operation['op'])) {
                            $operation['op'] = $this->getJsonPatchOperationType($operation['op']);
                        }
                    }
                }
                break;
        }

        return $eventData;
    }

    /**
     * Gets the JsonPatchOperationType constant for a given operation string.
     */
    private function getJsonPatchOperationType(string $op): int
    {
        $constant = 'Ag_ui\\JsonPatchOperationType::' . strtoupper($op);
        
        if (!defined($constant)) {
            throw new InvalidArgumentException("Unknown JSON patch operation: $op");
        }
        
        return constant($constant);
    }

    /**
     * Converts a PHP value to a Google\Protobuf\Value.
     */
    private function convertToValue($value): ?Value
    {
        if ($value === null) {
            return null;
        }

        $protoValue = new Value();
        
        if (is_string($value)) {
            $protoValue->setStringValue($value);
        } elseif (is_int($value) || is_float($value)) {
            $protoValue->setNumberValue((float)$value);
        } elseif (is_bool($value)) {
            $protoValue->setBoolValue($value);
        } elseif (is_array($value)) {
            // This is simplified - full implementation would handle nested structures
            $protoValue->setStringValue(json_encode($value));
        } else {
            $protoValue->setStringValue((string)$value);
        }

        return $protoValue;
    }

    /**
     * Converts a Google\Protobuf\Value to a PHP value.
     */
    private function convertFromValue(?Value $value)
    {
        if ($value === null) {
            return null;
        }

        $kind = $value->getKind();
        
        if ($kind === 'string_value') {
            return $value->getStringValue();
        } elseif ($kind === 'number_value') {
            return $value->getNumberValue();
        } elseif ($kind === 'bool_value') {
            return $value->getBoolValue();
        } elseif ($kind === 'null_value') {
            return null;
        }

        // Default to string representation
        return (string)$value;
    }

    /**
     * Extracts event-specific data from a protobuf message.
     */
    private function extractEventData($eventMessage): array
    {
        $data = [];
        $reflection = new \ReflectionClass($eventMessage);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            if (strpos($methodName, 'get') === 0 && 
                $methodName !== 'getBaseEvent' && 
                $methodName !== 'getDescriptor') {
                
                $fieldName = $this->fromCamelCase(substr($methodName, 3));
                $value = $eventMessage->$methodName();
                
                if ($value !== null) {
                    // Handle special cases for complex types
                    if (is_object($value) && method_exists($value, 'getIterator')) {
                        // RepeatedField - convert to array
                        $data[strtolower($fieldName)] = iterator_to_array($value);
                    } else {
                        $data[strtolower($fieldName)] = $value;
                    }
                }
            }
        }

        return $data;
    }
}

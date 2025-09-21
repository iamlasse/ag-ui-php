<?php

declare(strict_types=1);

namespace AGUI\Encoder;

use AGUI\Core\Events\EventType;
use AGUI\Core\Validation\ValidationException;

/**
 * JSON Schema validator for AG-UI events
 *
 * @package AGUI\Encoder
 */
final class SchemaValidator
{
    /**
     * Event schema definitions
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $schemas = [
        'base' => [
            'type' => 'object',
            'required' => ['id', 'type', 'schema_version'],
            'properties' => [
                'id' => ['type' => 'string', 'minLength' => 1],
                'type' => ['type' => 'string', 'enum' => []],  // Will be populated dynamically
                'runId' => ['type' => 'string', 'minLength' => 1],
                'timestamp' => ['type' => 'integer', 'minimum' => 0],
                'metadata' => ['type' => 'object'],
                'schema_version' => ['type' => 'string', 'minLength' => 1],
                'context' => ['type' => 'object']
            ]
        ],
        'run_started' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['runId'],
                    'properties' => [
                        'type' => ['const' => 'run_started'],
                        'agentName' => ['type' => 'string'],
                        'input' => ['type' => 'object'],
                        'config' => ['type' => 'object']
                    ]
                ]
            ]
        ],
        'run_finished' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['runId', 'success'],
                    'properties' => [
                        'type' => ['const' => 'run_finished'],
                        'success' => ['type' => 'boolean'],
                        'result' => ['type' => 'string'],
                        'error' => ['type' => 'string'],
                        'duration' => ['type' => 'integer', 'minimum' => 0]
                    ]
                ]
            ]
        ],
        'text_message_start' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['message'],
                    'properties' => [
                        'type' => ['const' => 'text_message_start'],
                        'message' => ['type' => 'object']
                    ]
                ]
            ]
        ],
        'text_message_chunk' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['messageId', 'content'],
                    'properties' => [
                        'type' => ['const' => 'text_message_chunk'],
                        'messageId' => ['type' => 'string', 'minLength' => 1],
                        'content' => ['type' => 'string'],
                        'chunkIndex' => ['type' => 'integer', 'minimum' => 0],
                        'isLast' => ['type' => 'boolean']
                    ]
                ]
            ]
        ],
        'text_message_end' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['messageId'],
                    'properties' => [
                        'type' => ['const' => 'text_message_end'],
                        'messageId' => ['type' => 'string', 'minLength' => 1],
                        'finalContent' => ['type' => 'string'],
                        'totalChunks' => ['type' => 'integer', 'minimum' => 0]
                    ]
                ]
            ]
        ],
        'tool_call_start' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['toolCall'],
                    'properties' => [
                        'type' => ['const' => 'tool_call_start'],
                        'toolCall' => ['type' => 'object']
                    ]
                ]
            ]
        ],
        'tool_call_chunk' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['toolCallId'],
                    'properties' => [
                        'type' => ['const' => 'tool_call_chunk'],
                        'toolCallId' => ['type' => 'string', 'minLength' => 1],
                        'content' => ['type' => 'string'],
                        'chunkIndex' => ['type' => 'integer', 'minimum' => 0],
                        'isLast' => ['type' => 'boolean']
                    ]
                ]
            ]
        ],
        'tool_call_end' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['toolCallId'],
                    'properties' => [
                        'type' => ['const' => 'tool_call_end'],
                        'toolCallId' => ['type' => 'string', 'minLength' => 1],
                        'finalResult' => ['type' => 'string'],
                        'totalChunks' => ['type' => 'integer', 'minimum' => 0],
                        'success' => ['type' => 'boolean'],
                        'error' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'state_snapshot' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['state'],
                    'properties' => [
                        'type' => ['const' => 'state_snapshot'],
                        'state' => ['type' => 'object'],
                        'stateId' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'state_delta' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['patches'],
                    'properties' => [
                        'type' => ['const' => 'state_delta'],
                        'patches' => ['type' => 'array'],
                        'stateId' => ['type' => 'string'],
                        'previousStateId' => ['type' => 'string']
                    ]
                ]
            ]
        ],
        'messages_snapshot' => [
            'allOf' => [
                ['$ref' => '#/definitions/base'],
                [
                    'type' => 'object',
                    'required' => ['messages'],
                    'properties' => [
                        'type' => ['const' => 'messages_snapshot'],
                        'messages' => ['type' => 'array'],
                        'snapshotId' => ['type' => 'string'],
                        'totalMessages' => ['type' => 'integer', 'minimum' => 0]
                    ]
                ]
            ]
        ]
    ];

    private bool $initialized = false;

    /**
     * Initialize schemas with dynamic data
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Populate enum values for event types
        self::$schemas['base']['properties']['type']['enum'] = EventType::all();
        
        $this->initialized = true;
    }

    /**
     * Validate event data against its schema
     *
     * @param array<string, mixed> $data Event data to validate
     * @throws ValidationException If validation fails
     */
    public function validateEvent(array $data): void
    {
        $this->initialize();

        if (!isset($data['type'])) {
            throw new ValidationException('Event data missing type field');
        }

        $eventType = $data['type'];
        if (!is_string($eventType)) {
            throw new ValidationException('Event type must be a string');
        }

        // Validate against base schema first
        $this->validateAgainstSchema($data, self::$schemas['base'], 'base');

        // Validate against specific event type schema if it exists
        if (isset(self::$schemas[$eventType])) {
            $this->validateAgainstSchema($data, self::$schemas[$eventType], $eventType);
        }
    }

    /**
     * Get schema for a specific event type
     *
     * @param string $eventType Event type
     * @return array<string, mixed> Schema definition
     * @throws ValidationException If event type not supported
     */
    public function getSchema(string $eventType): array
    {
        $this->initialize();

        if (!isset(self::$schemas[$eventType])) {
            throw new ValidationException("Schema not found for event type: {$eventType}");
        }

        return self::$schemas[$eventType];
    }

    /**
     * Check if schema exists for event type
     *
     * @param string $eventType Event type to check
     * @return bool True if schema exists
     */
    public function hasSchema(string $eventType): bool
    {
        $this->initialize();
        return isset(self::$schemas[$eventType]);
    }

    /**
     * Get all available schemas
     *
     * @return array<string, array<string, mixed>> All schemas
     */
    public function getAllSchemas(): array
    {
        $this->initialize();
        return self::$schemas;
    }

    /**
     * Validate data against a specific schema
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, mixed> $schema Schema definition
     * @param string $schemaName Schema name for error reporting
     * @throws ValidationException If validation fails
     */
    private function validateAgainstSchema(array $data, array $schema, string $schemaName): void
    {
        // Basic type validation
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!is_array($data)) {
                throw new ValidationException("Schema {$schemaName}: expected object, got " . gettype($data));
            }
        }

        // Required fields validation
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!array_key_exists($requiredField, $data)) {
                    throw new ValidationException("Schema {$schemaName}: missing required field '{$requiredField}'");
                }
            }
        }

        // Properties validation
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                if (array_key_exists($property, $data)) {
                    $this->validateProperty($data[$property], $propertySchema, $property, $schemaName);
                }
            }
        }

        // Handle allOf (inheritance-like behavior)
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $subSchema) {
                if (isset($subSchema['$ref'])) {
                    // Handle schema references (simplified for this implementation)
                    if ($subSchema['$ref'] === '#/definitions/base') {
                        $this->validateAgainstSchema($data, self::$schemas['base'], 'base');
                    }
                } else {
                    $this->validateAgainstSchema($data, $subSchema, $schemaName);
                }
            }
        }
    }

    /**
     * Validate individual property against its schema
     *
     * @param mixed $value Property value
     * @param array<string, mixed> $propertySchema Property schema
     * @param string $propertyName Property name for error reporting
     * @param string $schemaName Schema name for error reporting
     * @throws ValidationException If validation fails
     */
    private function validateProperty($value, array $propertySchema, string $propertyName, string $schemaName): void
    {
        // Type validation
        if (isset($propertySchema['type'])) {
            $expectedType = $propertySchema['type'];
            $actualType = $this->getJsonType($value);
            
            if ($actualType !== $expectedType) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: expected {$expectedType}, got {$actualType}");
            }
        }

        // Const validation
        if (isset($propertySchema['const'])) {
            if ($value !== $propertySchema['const']) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: expected constant value '{$propertySchema['const']}', got '{$value}'");
            }
        }

        // Enum validation
        if (isset($propertySchema['enum']) && is_array($propertySchema['enum'])) {
            if (!in_array($value, $propertySchema['enum'], true)) {
                $allowedValues = implode(', ', $propertySchema['enum']);
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: value '{$value}' not in allowed values: {$allowedValues}");
            }
        }

        // String length validation
        if (is_string($value)) {
            if (isset($propertySchema['minLength']) && strlen($value) < $propertySchema['minLength']) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: string too short (minimum {$propertySchema['minLength']} characters)");
            }
            if (isset($propertySchema['maxLength']) && strlen($value) > $propertySchema['maxLength']) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: string too long (maximum {$propertySchema['maxLength']} characters)");
            }
        }

        // Numeric validation
        if (is_int($value) || is_float($value)) {
            if (isset($propertySchema['minimum']) && $value < $propertySchema['minimum']) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: value {$value} below minimum {$propertySchema['minimum']}");
            }
            if (isset($propertySchema['maximum']) && $value > $propertySchema['maximum']) {
                throw new ValidationException("Schema {$schemaName}.{$propertyName}: value {$value} above maximum {$propertySchema['maximum']}");
            }
        }
    }

    /**
     * Get JSON schema type for a PHP value
     *
     * @param mixed $value Value to check
     * @return string JSON schema type
     */
    private function getJsonType($value): string
    {
        return match (gettype($value)) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'double' => 'number',
            'string' => 'string',
            'array' => 'array',
            'object' => 'object',
            'NULL' => 'null',
            default => 'unknown'
        };
    }
}

<?php

declare(strict_types=1);

namespace AGUI\Tests\Encoder;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Validation\ValidationException;
use AGUI\Encoder\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for SchemaValidator
 */
final class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testValidateRunStartedEvent(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'test-agent');
        $eventData = array_merge($event->getFullData(), ['schema_version' => '1.0']);
        
        // Should not throw any exception
        $this->validator->validateEvent($eventData);
        
        $this->assertTrue(true); // Assertion to ensure the test passes
    }

    public function testValidateRunFinishedEvent(): void
    {
        $event = EventFactory::createRunFinished('run-123', true, 'Success!');
        $eventData = array_merge($event->getFullData(), ['schema_version' => '1.0']);
        
        // Should not throw any exception
        $this->validator->validateEvent($eventData);
        
        $this->assertTrue(true);
    }

    public function testValidateTextMessageEvents(): void
    {
        $events = [
            EventFactory::createTextMessageChunk('msg-1', 'Hello, world!'),
            EventFactory::createTextMessageEnd('msg-1', 'Hello, world!'),
        ];

        foreach ($events as $event) {
            $eventData = array_merge($event->getFullData(), ['schema_version' => '1.0']);
            
            // Should not throw any exception
            $this->validator->validateEvent($eventData);
        }
        
        $this->assertTrue(true);
    }

    public function testValidateToolCallEvents(): void
    {
        $events = [
            EventFactory::createToolCallChunk('tool-1', 'chunk content'),
            EventFactory::createToolCallEnd('tool-1', 'final result'),
        ];

        foreach ($events as $event) {
            $eventData = array_merge($event->getFullData(), ['schema_version' => '1.0']);
            
            // Should not throw any exception
            $this->validator->validateEvent($eventData);
        }
        
        $this->assertTrue(true);
    }

    public function testValidateStateEvents(): void
    {
        $events = [
            EventFactory::createStateSnapshot(['key' => 'value'], 'state-1'),
            EventFactory::createStateDelta([['op' => 'add', 'path' => '/key', 'value' => 'new_value']], 'state-2'),
            EventFactory::createMessagesSnapshot([], 'snapshot-1'),
        ];

        foreach ($events as $event) {
            $eventData = array_merge($event->getFullData(), ['schema_version' => '1.0']);
            
            // Should not throw any exception
            $this->validator->validateEvent($eventData);
        }
        
        $this->assertTrue(true);
    }

    public function testValidationFailsWithMissingType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('missing type field');
        
        $this->validator->validateEvent([
            'id' => 'test-id',
            'schema_version' => '1.0'
        ]);
    }

    public function testValidationFailsWithInvalidType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Event type must be a string');
        
        $this->validator->validateEvent([
            'id' => 'test-id',
            'type' => 123,
            'schema_version' => '1.0'
        ]);
    }

    public function testValidationFailsWithMissingRequiredFields(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('missing required field');
        
        $this->validator->validateEvent([
            'type' => 'run_started',
            'schema_version' => '1.0'
            // Missing 'id' field
        ]);
    }

    public function testValidationFailsWithInvalidEventType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('value \'unknown_type\' not in allowed values');
        
        $this->validator->validateEvent([
            'id' => 'test-id',
            'type' => 'unknown_type',
            'schema_version' => '1.0'
        ]);
    }

    public function testValidationFailsWithWrongDataType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('expected string, got integer');
        
        $this->validator->validateEvent([
            'id' => 123, // Should be string
            'type' => 'run_started',
            'schema_version' => '1.0'
        ]);
    }

    public function testValidationFailsWithEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('string too short');
        
        $this->validator->validateEvent([
            'id' => '', // Empty string violates minLength
            'type' => 'run_started',
            'schema_version' => '1.0'
        ]);
    }

    public function testValidationFailsWithNegativeTimestamp(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('below minimum');
        
        $this->validator->validateEvent([
            'id' => 'test-id',
            'type' => 'run_started',
            'schema_version' => '1.0',
            'timestamp' => -1 // Negative timestamp violates minimum constraint
        ]);
    }

    public function testValidationFailsWithMissingSpecificRequiredFields(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('missing required field \'runId\'');
        
        $this->validator->validateEvent([
            'id' => 'test-id',
            'type' => 'run_started',
            'schema_version' => '1.0'
            // Missing 'runId' which is required for run_started events
        ]);
    }

    public function testValidationFailsWithWrongConstValue(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('expected constant value \'run_started\'');
        
        // This should fail because we're validating against run_started schema but type is different
        $eventData = [
            'id' => 'test-id',
            'type' => 'run_finished', // Wrong const value
            'schema_version' => '1.0',
            'runId' => 'test-run'
        ];
        
        // Force validation against run_started schema
        $schema = $this->validator->getSchema('run_started');
        $this->callPrivateMethod('validateAgainstSchema', [$eventData, $schema, 'run_started']);
    }

    public function testGetSchema(): void
    {
        $schema = $this->validator->getSchema('run_started');
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('allOf', $schema);
    }

    public function testGetSchemaForUnknownType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Schema not found for event type: unknown_type');
        
        $this->validator->getSchema('unknown_type');
    }

    public function testHasSchema(): void
    {
        $this->assertTrue($this->validator->hasSchema('run_started'));
        $this->assertTrue($this->validator->hasSchema('base'));
        $this->assertFalse($this->validator->hasSchema('unknown_type'));
    }

    public function testGetAllSchemas(): void
    {
        $schemas = $this->validator->getAllSchemas();
        
        $this->assertIsArray($schemas);
        $this->assertArrayHasKey('base', $schemas);
        $this->assertArrayHasKey('run_started', $schemas);
        $this->assertArrayHasKey('run_finished', $schemas);
        $this->assertArrayHasKey('text_message_start', $schemas);
        $this->assertArrayHasKey('text_message_chunk', $schemas);
        $this->assertArrayHasKey('text_message_end', $schemas);
        $this->assertArrayHasKey('tool_call_start', $schemas);
        $this->assertArrayHasKey('tool_call_chunk', $schemas);
        $this->assertArrayHasKey('tool_call_end', $schemas);
        $this->assertArrayHasKey('state_snapshot', $schemas);
        $this->assertArrayHasKey('state_delta', $schemas);
        $this->assertArrayHasKey('messages_snapshot', $schemas);
    }

    public function testValidationWithOptionalFields(): void
    {
        $eventData = [
            'id' => 'test-id',
            'type' => 'run_started',
            'schema_version' => '1.0',
            'runId' => 'test-run',
            'agentName' => 'test-agent', // Optional field
            'metadata' => ['key' => 'value'], // Optional field
            'timestamp' => time() // Optional field
        ];
        
        // Should not throw any exception
        $this->validator->validateEvent($eventData);
        
        $this->assertTrue(true);
    }

    /**
     * Helper method to call private methods for testing
     *
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    private function callPrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->validator, $parameters);
    }

    public function testJsonTypeDetection(): void
    {
        $getJsonType = fn($value) => $this->callPrivateMethod('getJsonType', [$value]);
        
        $this->assertEquals('boolean', $getJsonType(true));
        $this->assertEquals('boolean', $getJsonType(false));
        $this->assertEquals('integer', $getJsonType(42));
        $this->assertEquals('number', $getJsonType(3.14));
        $this->assertEquals('string', $getJsonType('hello'));
        $this->assertEquals('array', $getJsonType([]));
        $this->assertEquals('object', $getJsonType(new \stdClass()));
        $this->assertEquals('null', $getJsonType(null));
    }

    public function testPropertyValidationWithStringLimits(): void
    {
        $validateProperty = fn($value, $schema, $name, $schemaName) => 
            $this->callPrivateMethod('validateProperty', [$value, $schema, $name, $schemaName]);
        
        // Test max length validation
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('string too long');
        
        $validateProperty(
            'This is a very long string that exceeds the maximum length',
            ['type' => 'string', 'maxLength' => 10],
            'testField',
            'testSchema'
        );
    }

    public function testPropertyValidationWithNumericLimits(): void
    {
        $validateProperty = fn($value, $schema, $name, $schemaName) => 
            $this->callPrivateMethod('validateProperty', [$value, $schema, $name, $schemaName]);
        
        // Test maximum validation
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('above maximum');
        
        $validateProperty(
            100,
            ['type' => 'integer', 'maximum' => 50],
            'testField',
            'testSchema'
        );
    }
}

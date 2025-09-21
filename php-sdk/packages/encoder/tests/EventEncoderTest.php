<?php

declare(strict_types=1);

namespace AGUI\Tests\Encoder;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Types\UserMessage;
use AGUI\Encoder\DecodingException;
use AGUI\Encoder\EncodingException;
use AGUI\Encoder\EventEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for EventEncoder
 */
final class EventEncoderTest extends TestCase
{
    private EventEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new EventEncoder();
    }

    public function testEncodeRunStartedEvent(): void
    {
        $event = EventFactory::createRunStarted(
            runId: 'test-run-123',
            agentName: 'test-agent',
            input: ['prompt' => 'Hello, world!'],
            config: ['temperature' => 0.7]
        );

        $encoded = $this->encoder->encode($event);
        
        $this->assertIsString($encoded);
        $this->assertJson($encoded);
        
        $decoded = json_decode($encoded, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('schema_version', $decoded);
        $this->assertEquals('run_started', $decoded['type']);
        $this->assertEquals('1.0', $decoded['schema_version']);
    }

    public function testDecodeRunStartedEvent(): void
    {
        $originalEvent = EventFactory::createRunStarted(
            runId: 'test-run-123',
            agentName: 'test-agent',
            input: ['prompt' => 'Hello, world!'],
            config: ['temperature' => 0.7]
        );

        $encoded = $this->encoder->encode($originalEvent);
        $decodedEvent = $this->encoder->decode($encoded);

        $this->assertInstanceOf(RunStarted::class, $decodedEvent);
        $this->assertEquals($originalEvent->getId(), $decodedEvent->getId());
        $this->assertEquals($originalEvent->getType(), $decodedEvent->getType());
        $this->assertEquals('test-run-123', $decodedEvent->getRunId());
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $events = [
            EventFactory::createRunStarted('run-1', 'agent-1'),
            EventFactory::createRunFinished('run-1', true, 'Success!'),
            EventFactory::createTextMessageChunk('msg-1', 'Hello, world!'),
        ];

        foreach ($events as $originalEvent) {
            $encoded = $this->encoder->encode($originalEvent);
            $decodedEvent = $this->encoder->decode($encoded);

            $this->assertEquals($originalEvent->getId(), $decodedEvent->getId());
            $this->assertEquals($originalEvent->getType(), $decodedEvent->getType());
            $this->assertEquals($originalEvent->getRunId(), $decodedEvent->getRunId());
        }
    }

    public function testEncodeMultipleEvents(): void
    {
        $events = [
            EventFactory::createRunStarted('run-1', 'agent-1'),
            EventFactory::createTextMessageChunk('msg-1', 'Hello!'),
            EventFactory::createRunFinished('run-1', true)
        ];

        $encoded = $this->encoder->encodeMultiple($events);
        
        $this->assertIsString($encoded);
        $this->assertJson($encoded);
        
        $decoded = json_decode($encoded, true);
        $this->assertArrayHasKey('schema_version', $decoded);
        $this->assertArrayHasKey('events', $decoded);
        $this->assertArrayHasKey('encoded_at', $decoded);
        $this->assertCount(3, $decoded['events']);
    }

    public function testDecodeMultipleEvents(): void
    {
        $originalEvents = [
            EventFactory::createRunStarted('run-1', 'agent-1'),
            EventFactory::createTextMessageChunk('msg-1', 'Hello!'),
            EventFactory::createRunFinished('run-1', true)
        ];

        $encoded = $this->encoder->encodeMultiple($originalEvents);
        $decodedEvents = $this->encoder->decodeMultiple($encoded);

        $this->assertCount(3, $decodedEvents);
        
        foreach ($decodedEvents as $index => $decodedEvent) {
            $this->assertEquals($originalEvents[$index]->getId(), $decodedEvent->getId());
            $this->assertEquals($originalEvents[$index]->getType(), $decodedEvent->getType());
        }
    }

    public function testGetEventType(): void
    {
        $event = EventFactory::createRunStarted('run-1', 'agent-1');
        $encoded = $this->encoder->encode($event);
        
        $eventType = $this->encoder->getEventType($encoded);
        
        $this->assertEquals('run_started', $eventType);
    }

    public function testIsValidEvent(): void
    {
        $event = EventFactory::createRunStarted('run-1', 'agent-1');
        $encoded = $this->encoder->encode($event);
        
        $this->assertTrue($this->encoder->isValidEvent($encoded));
        $this->assertFalse($this->encoder->isValidEvent('{"invalid": "json"}'));
        $this->assertFalse($this->encoder->isValidEvent('invalid json'));
    }

    public function testSchemaValidationCanBeDisabled(): void
    {
        $encoder = new EventEncoder(validateSchema: false);
        
        $this->assertFalse($encoder->isSchemaValidationEnabled());
        
        // Should not throw even with invalid data when validation is disabled
        $invalidJson = '{"type": "unknown_type", "id": "test"}';
        $this->assertFalse($encoder->isValidEvent($invalidJson));
    }

    public function testTimestampHandling(): void
    {
        $encoder = new EventEncoder(includeTimestamp: true);
        $event = EventFactory::createRunStarted('run-1', 'agent-1');
        
        $encoded = $encoder->encode($event);
        $decoded = json_decode($encoded, true);
        
        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertIsInt($decoded['timestamp']);
        $this->assertGreaterThan(0, $decoded['timestamp']);
    }

    public function testTimestampCanBeDisabled(): void
    {
        $encoder = new EventEncoder(includeTimestamp: false);
        $event = EventFactory::createRunStarted('run-1', 'agent-1');
        
        $encoded = $encoder->encode($event);
        $decoded = json_decode($encoded, true);
        
        // Should not add timestamp if event doesn't already have one and auto-timestamp is disabled
        if (!$event->getTimestamp()) {
            $this->assertArrayNotHasKey('timestamp', $decoded);
        }
    }

    public function testEncodeWithContext(): void
    {
        $event = EventFactory::createRunStarted('run-1', 'agent-1');
        $context = ['user_id' => 'user123', 'session_id' => 'session456'];
        
        $encoded = $this->encoder->encode($event, $context);
        $decoded = json_decode($encoded, true);
        
        $this->assertArrayHasKey('context', $decoded);
        $this->assertEquals($context, $decoded['context']);
    }

    public function testEncodingExceptionOnInvalidEvent(): void
    {
        $this->expectException(EncodingException::class);
        
        // Create a mock event that will fail JSON encoding
        $event = $this->createMock(\AGUI\Core\Events\BaseEvent::class);
        $event->method('getFullData')->willReturn(['invalid' => fopen('php://memory', 'r')]);
        
        $this->encoder->encode($event);
    }

    public function testDecodingExceptionOnInvalidJson(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('JSON decoding failed');
        
        $this->encoder->decode('invalid json');
    }

    public function testDecodingExceptionOnMissingType(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('missing required "type" field');
        
        $this->encoder->decode('{"id": "test", "schema_version": "1.0"}');
    }

    public function testDecodingExceptionOnInvalidEventType(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('Invalid event type');
        
        $this->encoder->decode('{"id": "test", "type": "unknown_type", "schema_version": "1.0"}');
    }

    public function testGetEventTypeException(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('missing or invalid type field');
        
        $this->encoder->getEventType('{"id": "test"}');
    }

    public function testSchemaVersion(): void
    {
        $this->assertEquals('1.0', $this->encoder->getSchemaVersion());
    }

    public function testFluentConfigurationInterface(): void
    {
        $encoder = $this->encoder
            ->setSchemaValidation(false)
            ->setTimestampEnabled(false);
        
        $this->assertSame($this->encoder, $encoder);
        $this->assertFalse($encoder->isSchemaValidationEnabled());
        $this->assertFalse($encoder->isTimestampEnabled());
    }

    public function testEncodeMultipleWithInvalidEvent(): void
    {
        $this->expectException(EncodingException::class);
        $this->expectExceptionMessage('not a BaseEvent instance');
        
        $events = [
            EventFactory::createRunStarted('run-1', 'agent-1'),
            'not an event',
            EventFactory::createRunFinished('run-1', true)
        ];
        
        $this->encoder->encodeMultiple($events);
    }

    public function testDecodeMultipleWithInvalidFormat(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('events must be an array');
        
        $this->encoder->decodeMultiple('{"events": "not an array"}');
    }

    public function testDecodeMultipleWithInvalidEvent(): void
    {
        $this->expectException(DecodingException::class);
        $this->expectExceptionMessage('Invalid event at index');
        
        $invalidData = [
            'events' => [
                ['type' => 'run_started', 'id' => 'test-1', 'runId' => 'run-1'],
                'not an object',
                ['type' => 'run_finished', 'id' => 'test-3', 'success' => true]
            ]
        ];
        
        $this->encoder->decodeMultiple(json_encode($invalidData));
    }

    public function testPerformanceWithLargeEvents(): void
    {
        // Test with a large event to ensure performance is reasonable
        $largeInput = array_fill(0, 1000, 'test data');
        $event = EventFactory::createRunStarted('run-1', 'agent-1', $largeInput);
        
        $startTime = microtime(true);
        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        $endTime = microtime(true);
        
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->assertLessThan(100, $duration, 'Encoding/decoding should complete within 100ms');
        $this->assertEquals($event->getId(), $decoded->getId());
    }

    public function testMaxJsonDepthProtection(): void
    {
        // Create deeply nested data that exceeds max depth
        $deeplyNested = [];
        $current = &$deeplyNested;
        
        for ($i = 0; $i < 600; $i++) { // Exceed MAX_JSON_DEPTH of 512
            $current['nested'] = [];
            $current = &$current['nested'];
        }
        
        $event = EventFactory::createRunStarted('run-1', 'agent-1', $deeplyNested);
        
        $this->expectException(EncodingException::class);
        $this->encoder->encode($event);
    }
}

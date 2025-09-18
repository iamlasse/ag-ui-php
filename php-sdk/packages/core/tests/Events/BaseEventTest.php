<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for BaseEvent abstract class
 *
 * @package AGUI\Core\Tests\Events
 */
class BaseEventTest extends TestCase
{
    /**
     * Create a concrete implementation of BaseEvent for testing
     */
    private function createTestEvent(
        string $id,
        EventType $type,
        ?string $runId = null,
        ?int $timestamp = null,
        ?array $metadata = null
    ): BaseEvent {
        return new class($id, $type, $runId, $timestamp, $metadata) extends BaseEvent {
            public function getEventData(): array
            {
                return ['test' => 'data'];
            }
        };
    }

    /**
     * Test basic event creation
     */
    public function testEventCreation(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);

        $this->assertEquals('test-id', $event->getId());
        $this->assertEquals(EventType::RUN_STARTED, $event->getType());
        $this->assertNull($event->getRunId());
        $this->assertNull($event->getMetadata());
    }

    /**
     * Test event with optional parameters
     */
    public function testEventWithOptionalParameters(): void
    {
        $metadata = ['key' => 'value'];
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED, 'run-123', 1234567890, $metadata);

        $this->assertEquals('test-id', $event->getId());
        $this->assertEquals(EventType::RUN_STARTED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertEquals(1234567890, $event->getTimestamp());
        $this->assertEquals($metadata, $event->getMetadata());
    }

    /**
     * Test timestamp fallback
     */
    public function testTimestampFallback(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);

        // Should use current time when no timestamp is provided
        $timestamp = $event->getTimestamp();
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(time() - 10, $timestamp);
        $this->assertLessThan(time() + 10, $timestamp);
    }

    /**
     * Test toArray conversion
     */
    public function testToArrayConversion(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED, 'run-123', 1234567890, ['key' => 'value']);
        $array = $event->toArray();

        $expected = [
            'id' => 'test-id',
            'type' => 'run_started',
            'runId' => 'run-123',
            'timestamp' => 1234567890,
            'metadata' => ['key' => 'value']
        ];

        $this->assertEquals($expected, $array);
    }

    /**
     * Test toArray with minimal data
     */
    public function testToArrayWithMinimalData(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);
        $array = $event->toArray();

        $expected = [
            'id' => 'test-id',
            'type' => 'run_started'
        ];

        $this->assertEquals($expected, $array);
    }

    /**
     * Test toJson conversion
     */
    public function testToJsonConversion(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED, 'run-123', 1234567890, ['key' => 'value']);
        $json = $event->toJson();

        $this->assertJson($json);
        $data = json_decode($json, true);

        $this->assertEquals('test-id', $data['id']);
        $this->assertEquals('run_started', $data['type']);
        $this->assertEquals('run-123', $data['runId']);
    }

    /**
     * Test getFullData includes event data
     */
    public function testGetFullData(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);
        $fullData = $event->getFullData();

        $this->assertArrayHasKey('id', $fullData);
        $this->assertArrayHasKey('type', $fullData);
        $this->assertArrayHasKey('test', $fullData);
        $this->assertEquals('data', $fullData['test']);
    }

    /**
     * Test withMetadata method
     */
    public function testWithMetadata(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);
        $newEvent = $event->withMetadata(['new' => 'metadata']);

        $this->assertEquals(['new' => 'metadata'], $newEvent->getMetadata());
        $this->assertEquals('test-id', $newEvent->getId());
        $this->assertEquals(EventType::RUN_STARTED, $newEvent->getType());
    }

    /**
     * Test withRunId method
     */
    public function testWithRunId(): void
    {
        $event = $this->createTestEvent('test-id', EventType::RUN_STARTED);
        $newEvent = $event->withRunId('new-run-id');

        $this->assertEquals('new-run-id', $newEvent->getRunId());
        $this->assertEquals('test-id', $newEvent->getId());
        $this->assertEquals(EventType::RUN_STARTED, $newEvent->getType());
    }

    /**
     * Test validation throws exception for invalid data
     */
    public function testValidationThrowsException(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        // This should throw an exception because empty string is not allowed for id
        new class('', EventType::RUN_STARTED) extends BaseEvent {
            public function getEventData(): array
            {
                return [];
            }
        };
    }
}
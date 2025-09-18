<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\ToolCallStart;
use AGUI\Core\Events\ToolCallChunk;
use AGUI\Core\Events\ToolCallEnd;
use AGUI\Core\Types\ToolCall;
use AGUI\Core\Types\FunctionCall;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for tool call events
 *
 * @package AGUI\Core\Tests\Events
 */
class ToolCallEventsTest extends TestCase
{
    /**
     * Helper method to create a test tool call
     */
    private function createTestToolCall(): ToolCall
    {
        return new ToolCall('tool-123', 'function', new FunctionCall('test_function', '{"param": "value"}'));
    }

    /**
     * Test ToolCallStart creation
     */
    public function testToolCallStartCreation(): void
    {
        $toolCall = $this->createTestToolCall();
        $event = EventFactory::createToolCallStart($toolCall, 'run-123');

        $this->assertInstanceOf(ToolCallStart::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_START, $event->getType());
        $this->assertEquals($toolCall, $event->getToolCall());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test ToolCallStart array conversion
     */
    public function testToolCallStartToArray(): void
    {
        $toolCall = $this->createTestToolCall();
        $event = EventFactory::createToolCallStart($toolCall);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('tool_call_start', $array['type']);
    }

    /**
     * Test ToolCallStart event data
     */
    public function testToolCallStartEventData(): void
    {
        $toolCall = $this->createTestToolCall();
        $event = EventFactory::createToolCallStart($toolCall);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('toolCall', $eventData);
        $this->assertEquals($toolCall->toArray(), $eventData['toolCall']);
    }

    /**
     * Test ToolCallStart withToolCall
     */
    public function testToolCallStartWithToolCall(): void
    {
        $toolCall1 = $this->createTestToolCall();
        $toolCall2 = new ToolCall('tool-456', 'function', new FunctionCall('another_function', '{}'));
        $event = EventFactory::createToolCallStart($toolCall1);
        $newEvent = $event->withToolCall($toolCall2);

        $this->assertEquals($toolCall2, $newEvent->getToolCall());
        $this->assertEquals($event->getId(), $newEvent->getId());
    }

    /**
     * Test ToolCallChunk creation
     */
    public function testToolCallChunkCreation(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'chunk content', 0, false, 'run-123');

        $this->assertInstanceOf(ToolCallChunk::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_CHUNK, $event->getType());
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertEquals('chunk content', $event->getContent());
        $this->assertEquals(0, $event->getChunkIndex());
        $this->assertFalse($event->isLast());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test ToolCallChunk with null content
     */
    public function testToolCallChunkWithNullContent(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', null, 0, false, 'run-123');

        $this->assertInstanceOf(ToolCallChunk::class, $event);
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertNull($event->getContent());
        $this->assertEquals(0, $event->getChunkIndex());
        $this->assertFalse($event->isLast());
    }

    /**
     * Test ToolCallChunk with minimal parameters
     */
    public function testToolCallChunkMinimal(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123');

        $this->assertInstanceOf(ToolCallChunk::class, $event);
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertNull($event->getContent());
        $this->assertNull($event->getChunkIndex());
        $this->assertNull($event->isLast());
    }

    /**
     * Test ToolCallChunk array conversion
     */
    public function testToolCallChunkToArray(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'chunk content', 0, true);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('tool_call_chunk', $array['type']);
    }

    /**
     * Test ToolCallChunk event data
     */
    public function testToolCallChunkEventData(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'chunk content', 0, true);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('toolCallId', $eventData);
        $this->assertArrayHasKey('content', $eventData);
        $this->assertArrayHasKey('chunkIndex', $eventData);
        $this->assertArrayHasKey('isLast', $eventData);
        $this->assertEquals('tool-123', $eventData['toolCallId']);
        $this->assertEquals('chunk content', $eventData['content']);
        $this->assertEquals(0, $eventData['chunkIndex']);
        $this->assertTrue($eventData['isLast']);
    }

    /**
     * Test ToolCallChunk event data with null content
     */
    public function testToolCallChunkEventDataWithNullContent(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', null, 0, true);
        $eventData = $event->getEventData();

        $this->assertArrayNotHasKey('content', $eventData);
        $this->assertArrayHasKey('toolCallId', $eventData);
        $this->assertEquals('tool-123', $eventData['toolCallId']);
    }

    /**
     * Test ToolCallChunk withContent
     */
    public function testToolCallChunkWithContent(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'original content');
        $newEvent = $event->withContent('new content');

        $this->assertEquals('new content', $newEvent->getContent());
        $this->assertEquals($event->getToolCallId(), $newEvent->getToolCallId());
    }

    /**
     * Test ToolCallChunk withContent to null
     */
    public function testToolCallChunkWithContentToNull(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'original content');
        $newEvent = $event->withContent(null);

        $this->assertNull($newEvent->getContent());
        $this->assertEquals($event->getToolCallId(), $newEvent->getToolCallId());
    }

    /**
     * Test ToolCallChunk withChunkIndex
     */
    public function testToolCallChunkWithChunkIndex(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'content');
        $newEvent = $event->withChunkIndex(5);

        $this->assertEquals(5, $newEvent->getChunkIndex());
        $this->assertEquals($event->getContent(), $newEvent->getContent());
    }

    /**
     * Test ToolCallChunk withIsLast
     */
    public function testToolCallChunkWithIsLast(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'content');
        $newEvent = $event->withIsLast(true);

        $this->assertTrue($newEvent->isLast());
        $this->assertEquals($event->getContent(), $newEvent->getContent());
    }

    /**
     * Test ToolCallEnd creation
     */
    public function testToolCallEndCreation(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123', 'final result', 5, true, null, 'run-123');

        $this->assertInstanceOf(ToolCallEnd::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_END, $event->getType());
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertEquals('final result', $event->getFinalResult());
        $this->assertEquals(5, $event->getTotalChunks());
        $this->assertTrue($event->isSuccess());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test ToolCallEnd with failure
     */
    public function testToolCallEndWithFailure(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123', null, 5, false, 'error message', 'run-123');

        $this->assertInstanceOf(ToolCallEnd::class, $event);
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertNull($event->getFinalResult());
        $this->assertEquals(5, $event->getTotalChunks());
        $this->assertFalse($event->isSuccess());
        $this->assertEquals('error message', $event->getError());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test ToolCallEnd with minimal parameters
     */
    public function testToolCallEndMinimal(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123');

        $this->assertInstanceOf(ToolCallEnd::class, $event);
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertNull($event->getFinalResult());
        $this->assertNull($event->getTotalChunks());
        $this->assertNull($event->isSuccess());
        $this->assertNull($event->getError());
    }

    /**
     * Test ToolCallEnd array conversion
     */
    public function testToolCallEndToArray(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123', 'final result', 5, true, 'error message');
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('tool_call_end', $array['type']);
    }

    /**
     * Test ToolCallEnd event data
     */
    public function testToolCallEndEventData(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123', 'final result', 5, true, 'error message');
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('toolCallId', $eventData);
        $this->assertArrayHasKey('finalResult', $eventData);
        $this->assertArrayHasKey('totalChunks', $eventData);
        $this->assertArrayHasKey('success', $eventData);
        $this->assertArrayHasKey('error', $eventData);
        $this->assertEquals('tool-123', $eventData['toolCallId']);
        $this->assertEquals('final result', $eventData['finalResult']);
        $this->assertEquals(5, $eventData['totalChunks']);
        $this->assertTrue($eventData['success']);
        $this->assertEquals('error message', $eventData['error']);
    }

    /**
     * Test ToolCallEnd event data with minimal parameters
     */
    public function testToolCallEndEventDataMinimal(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123');
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('toolCallId', $eventData);
        $this->assertEquals('tool-123', $eventData['toolCallId']);
        $this->assertArrayNotHasKey('finalResult', $eventData);
        $this->assertArrayNotHasKey('totalChunks', $eventData);
        $this->assertArrayNotHasKey('success', $eventData);
        $this->assertArrayNotHasKey('error', $eventData);
    }

    /**
     * Test ToolCallEnd withFinalResult
     */
    public function testToolCallEndWithFinalResult(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123');
        $newEvent = $event->withFinalResult('new result');

        $this->assertEquals('new result', $newEvent->getFinalResult());
        $this->assertEquals($event->getToolCallId(), $newEvent->getToolCallId());
    }

    /**
     * Test ToolCallEnd withSuccess
     */
    public function testToolCallEndWithSuccess(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123');
        $newEvent = $event->withSuccess(true);

        $this->assertTrue($newEvent->isSuccess());
        $this->assertEquals($event->getToolCallId(), $newEvent->getToolCallId());
    }

    /**
     * Test ToolCallEnd withError
     */
    public function testToolCallEndWithError(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123');
        $newEvent = $event->withError('error message');

        $this->assertEquals('error message', $newEvent->getError());
        $this->assertEquals($event->getToolCallId(), $newEvent->getToolCallId());
    }

    /**
     * Test validation for empty tool call ID
     */
    public function testValidationThrowsForEmptyToolCallId(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createToolCallStart($this->createTestToolCall(), '', '');
    }

    /**
     * Test validation for negative chunk index
     */
    public function testValidationForNegativeChunkIndex(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createToolCallChunk('tool-123', 'content', -1);
    }

    /**
     * Test validation for negative total chunks
     */
    public function testValidationForNegativeTotalChunks(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createToolCallEnd('tool-123', 'result', -1);
    }
}
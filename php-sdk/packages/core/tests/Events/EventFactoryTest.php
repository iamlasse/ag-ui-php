<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Events\RunFinished;
use AGUI\Core\Events\TextMessageStart;
use AGUI\Core\Events\StateSnapshot;
use AGUI\Core\Types\UserMessage;
use AGUI\Core\Types\Role;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for EventFactory
 *
 * @package AGUI\Core\Tests\Events
 */
class EventFactoryTest extends TestCase
{
    /**
     * Test createRunStarted
     */
    public function testCreateRunStarted(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'test-agent', ['input' => 'data'], ['config' => 'value']);

        $this->assertInstanceOf(RunStarted::class, $event);
        $this->assertEquals(EventType::RUN_STARTED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertEquals('test-agent', $event->getAgentName());
        $this->assertEquals(['input' => 'data'], $event->getInput());
        $this->assertEquals(['config' => 'value'], $event->getConfig());
    }

    /**
     * Test createRunStarted with minimal parameters
     */
    public function testCreateRunStartedMinimal(): void
    {
        $event = EventFactory::createRunStarted('run-123');

        $this->assertInstanceOf(RunStarted::class, $event);
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertNull($event->getAgentName());
        $this->assertNull($event->getInput());
        $this->assertNull($event->getConfig());
    }

    /**
     * Test createRunFinished
     */
    public function testCreateRunFinished(): void
    {
        $event = EventFactory::createRunFinished('run-123', true, 'result data', null, 1000);

        $this->assertInstanceOf(RunFinished::class, $event);
        $this->assertEquals(EventType::RUN_FINISHED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertTrue($event->isSuccess());
        $this->assertEquals('result data', $event->getResult());
        $this->assertEquals(1000, $event->getDuration());
    }

    /**
     * Test createRunFinished with failure
     */
    public function testCreateRunFinishedWithFailure(): void
    {
        $event = EventFactory::createRunFinished('run-123', false, null, 'error message');

        $this->assertInstanceOf(RunFinished::class, $event);
        $this->assertEquals(EventType::RUN_FINISHED, $event->getType());
        $this->assertFalse($event->isSuccess());
        $this->assertEquals('error message', $event->getError());
        $this->assertNull($event->getResult());
    }

    /**
     * Test createTextMessageStart
     */
    public function testCreateTextMessageStart(): void
    {
        $message = new UserMessage('msg-123', 'Hello world');
        $event = EventFactory::createTextMessageStart($message, 'run-123');

        $this->assertInstanceOf(TextMessageStart::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_START, $event->getType());
        $this->assertEquals($message, $event->getMessage());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createTextMessageChunk
     */
    public function testCreateTextMessageChunk(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-123', 'chunk content', 0, false, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\TextMessageChunk::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_CHUNK, $event->getType());
        $this->assertEquals('msg-123', $event->getMessageId());
        $this->assertEquals('chunk content', $event->getContent());
        $this->assertEquals(0, $event->getChunkIndex());
        $this->assertFalse($event->isLast());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createTextMessageEnd
     */
    public function testCreateTextMessageEnd(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-123', 'final content', 5, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\TextMessageEnd::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_END, $event->getType());
        $this->assertEquals('msg-123', $event->getMessageId());
        $this->assertEquals('final content', $event->getFinalContent());
        $this->assertEquals(5, $event->getTotalChunks());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createToolCallStart
     */
    public function testCreateToolCallStart(): void
    {
        $toolCall = new \AGUI\Core\Types\ToolCall('tool-123', 'function', new \AGUI\Core\Types\FunctionCall('test_function', '{}'));
        $event = EventFactory::createToolCallStart($toolCall, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\ToolCallStart::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_START, $event->getType());
        $this->assertEquals($toolCall, $event->getToolCall());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createToolCallChunk
     */
    public function testCreateToolCallChunk(): void
    {
        $event = EventFactory::createToolCallChunk('tool-123', 'chunk content', 0, false, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\ToolCallChunk::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_CHUNK, $event->getType());
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertEquals('chunk content', $event->getContent());
        $this->assertEquals(0, $event->getChunkIndex());
        $this->assertFalse($event->isLast());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createToolCallEnd
     */
    public function testCreateToolCallEnd(): void
    {
        $event = EventFactory::createToolCallEnd('tool-123', 'final result', 5, true, null, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\ToolCallEnd::class, $event);
        $this->assertEquals(EventType::TOOL_CALL_END, $event->getType());
        $this->assertEquals('tool-123', $event->getToolCallId());
        $this->assertEquals('final result', $event->getFinalResult());
        $this->assertEquals(5, $event->getTotalChunks());
        $this->assertTrue($event->isSuccess());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createStateSnapshot
     */
    public function testCreateStateSnapshot(): void
    {
        $state = ['key1' => 'value1', 'key2' => 'value2'];
        $event = EventFactory::createStateSnapshot($state, 'state-123', 'run-123');

        $this->assertInstanceOf(StateSnapshot::class, $event);
        $this->assertEquals(EventType::STATE_SNAPSHOT, $event->getType());
        $this->assertEquals($state, $event->getState());
        $this->assertEquals('state-123', $event->getStateId());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createStateDelta
     */
    public function testCreateStateDelta(): void
    {
        $patches = [
            ['op' => 'add', 'path' => '/key', 'value' => 'value'],
            ['op' => 'replace', 'path' => '/existing', 'value' => 'new']
        ];
        $event = EventFactory::createStateDelta($patches, 'state-123', 'state-122', 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\StateDelta::class, $event);
        $this->assertEquals(EventType::STATE_DELTA, $event->getType());
        $this->assertEquals($patches, $event->getPatches());
        $this->assertEquals('state-123', $event->getStateId());
        $this->assertEquals('state-122', $event->getPreviousStateId());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test createMessagesSnapshot
     */
    public function testCreateMessagesSnapshot(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there')
        ];
        $event = EventFactory::createMessagesSnapshot($messages, 'snapshot-123', 2, 'run-123');

        $this->assertInstanceOf(\AGUI\Core\Events\MessagesSnapshot::class, $event);
        $this->assertEquals(EventType::MESSAGES_SNAPSHOT, $event->getType());
        $this->assertEquals($messages, $event->getMessages());
        $this->assertEquals('snapshot-123', $event->getSnapshotId());
        $this->assertEquals(2, $event->getTotalMessages());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test auto-generated event IDs
     */
    public function testAutoGeneratedEventIds(): void
    {
        $event1 = EventFactory::createRunStarted('run-123');
        $event2 = EventFactory::createRunStarted('run-123');

        $this->assertNotEmpty($event1->getId());
        $this->assertNotEmpty($event2->getId());
        $this->assertNotEquals($event1->getId(), $event2->getId());
        $this->assertStringStartsWith('event_', $event1->getId());
    }

    /**
     * Test custom event ID
     */
    public function testCustomEventId(): void
    {
        $event = EventFactory::createRunStarted('run-123', null, null, null, 'custom-id');

        $this->assertEquals('custom-id', $event->getId());
    }

    /**
     * Test fromArray with RunStarted
     */
    public function testFromArrayRunStarted(): void
    {
        $data = [
            'type' => 'run_started',
            'id' => 'test-id',
            'runId' => 'run-123',
            'agentName' => 'test-agent',
            'input' => ['key' => 'value'],
            'config' => ['config' => 'data'],
            'timestamp' => 1234567890,
            'metadata' => ['meta' => 'data']
        ];

        $event = EventFactory::fromArray($data);

        $this->assertInstanceOf(RunStarted::class, $event);
        $this->assertEquals('test-id', $event->getId());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertEquals('test-agent', $event->getAgentName());
    }

    /**
     * Test fromArray with TextMessageStart
     */
    public function testFromArrayTextMessageStart(): void
    {
        $data = [
            'type' => 'text_message_start',
            'id' => 'test-id',
            'runId' => 'run-123',
            'message' => [
                'id' => 'msg-123',
                'role' => 'user',
                'content' => 'Hello world'
            ],
            'timestamp' => 1234567890
        ];

        $event = EventFactory::fromArray($data);

        $this->assertInstanceOf(TextMessageStart::class, $event);
        $this->assertEquals('test-id', $event->getId());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertEquals('msg-123', $event->getMessage()->getId());
    }

    /**
     * Test fromArray with invalid type
     */
    public function testFromArrayInvalidType(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        $data = [
            'type' => 'invalid_type',
            'id' => 'test-id'
        ];

        EventFactory::fromArray($data);
    }

    /**
     * Test fromArray with missing required fields
     */
    public function testFromArrayMissingRequiredFields(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        $data = [
            'type' => 'run_started',
            'id' => 'test-id'
            // Missing runId
        ];

        EventFactory::fromArray($data);
    }

    /**
     * Test fromArray with invalid data structure
     */
    public function testFromArrayInvalidDataStructure(): void
    {
        $this->expectException(\TypeError::class);

        $data = 'invalid data structure';

        EventFactory::fromArray($data);
    }
}
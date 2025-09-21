<?php

declare(strict_types=1);

namespace AGUI\Tests\Proto;

use AGUI\Proto\EventTypes;
use AGUI\Proto\JsonPatchOperations;
use AGUI\Proto\Proto;
use AGUI\Proto\ProtoEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ProtoEncoder class to verify API compatibility
 */
class ProtoEncoderTest extends TestCase
{
    private ProtoEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new ProtoEncoder();
    }

    public function testTextMessageStartEvent(): void
    {
        $event = [
            'type' => EventTypes::TEXT_MESSAGE_START,
            'timestamp' => 1634567890123,
            'messageId' => 'msg-123',
            'role' => 'assistant'
        ];

        $encoded = $this->encoder->encode($event);
        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);

        $decoded = $this->encoder->decode($encoded);
        $this->assertEquals(EventTypes::TEXT_MESSAGE_START, $decoded['type']);
        $this->assertEquals(1634567890123, $decoded['timestamp']);
        $this->assertEquals('msg-123', $decoded['messageid']);
        $this->assertEquals('assistant', $decoded['role']);
    }

    public function testTextMessageContentEvent(): void
    {
        $event = [
            'type' => EventTypes::TEXT_MESSAGE_CONTENT,
            'messageId' => 'msg-123',
            'delta' => 'Hello, world!'
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::TEXT_MESSAGE_CONTENT, $decoded['type']);
        $this->assertEquals('msg-123', $decoded['messageid']);
        $this->assertEquals('Hello, world!', $decoded['delta']);
    }

    public function testToolCallStartEvent(): void
    {
        $event = [
            'type' => EventTypes::TOOL_CALL_START,
            'toolCallId' => 'tool-call-123',
            'toolCallName' => 'search',
            'parentMessageId' => 'msg-456'
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::TOOL_CALL_START, $decoded['type']);
        $this->assertEquals('tool-call-123', $decoded['toolcallid']);
        $this->assertEquals('search', $decoded['toolcallname']);
        $this->assertEquals('msg-456', $decoded['parentmessageid']);
    }

    public function testStateSnapshotEvent(): void
    {
        $event = [
            'type' => EventTypes::STATE_SNAPSHOT,
            'snapshot' => ['key' => 'value', 'count' => 42]
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::STATE_SNAPSHOT, $decoded['type']);
        $this->assertNotNull($decoded['snapshot']);
    }

    public function testStateDeltaEvent(): void
    {
        $event = [
            'type' => EventTypes::STATE_DELTA,
            'delta' => [
                [
                    'op' => JsonPatchOperations::ADD,
                    'path' => '/items/0',
                    'value' => 'new item'
                ],
                [
                    'op' => JsonPatchOperations::REPLACE,
                    'path' => '/count',
                    'value' => 5
                ]
            ]
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::STATE_DELTA, $decoded['type']);
        $this->assertIsArray($decoded['delta']);
        $this->assertCount(2, $decoded['delta']);
    }

    public function testMessagesSnapshotEvent(): void
    {
        $event = [
            'type' => EventTypes::MESSAGES_SNAPSHOT,
            'messages' => [
                [
                    'id' => 'msg-1',
                    'role' => 'user',
                    'content' => 'Hello',
                    'toolCalls' => []
                ],
                [
                    'id' => 'msg-2',
                    'role' => 'assistant',
                    'content' => 'Hi there!',
                    'toolCalls' => [
                        [
                            'id' => 'call-1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search',
                                'arguments' => '{"query": "test"}'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::MESSAGES_SNAPSHOT, $decoded['type']);
        $this->assertIsArray($decoded['messages']);
        $this->assertCount(2, $decoded['messages']);
    }

    public function testCustomEvent(): void
    {
        $event = [
            'type' => EventTypes::CUSTOM,
            'name' => 'my-custom-event',
            'value' => ['custom' => 'data']
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::CUSTOM, $decoded['type']);
        $this->assertEquals('my-custom-event', $decoded['name']);
    }

    public function testRunStartedEvent(): void
    {
        $event = [
            'type' => EventTypes::RUN_STARTED,
            'threadId' => 'thread-123',
            'runId' => 'run-456'
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::RUN_STARTED, $decoded['type']);
        $this->assertEquals('thread-123', $decoded['threadid']);
        $this->assertEquals('run-456', $decoded['runid']);
    }

    public function testRunFinishedEvent(): void
    {
        $event = [
            'type' => EventTypes::RUN_FINISHED,
            'threadId' => 'thread-123',
            'runId' => 'run-456',
            'result' => ['status' => 'success']
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::RUN_FINISHED, $decoded['type']);
        $this->assertEquals('thread-123', $decoded['threadid']);
        $this->assertEquals('run-456', $decoded['runid']);
    }

    public function testRunErrorEvent(): void
    {
        $event = [
            'type' => EventTypes::RUN_ERROR,
            'code' => 'INTERNAL_ERROR',
            'message' => 'Something went wrong'
        ];

        $encoded = $this->encoder->encode($event);
        $decoded = $this->encoder->decode($encoded);
        
        $this->assertEquals(EventTypes::RUN_ERROR, $decoded['type']);
        $this->assertEquals('INTERNAL_ERROR', $decoded['code']);
        $this->assertEquals('Something went wrong', $decoded['message']);
    }

    public function testProtoFacade(): void
    {
        $event = [
            'type' => EventTypes::TEXT_MESSAGE_START,
            'messageId' => 'facade-test',
            'role' => 'user'
        ];

        $encoded = Proto::encode($event);
        $this->assertIsString($encoded);

        $decoded = Proto::decode($encoded);
        $this->assertEquals(EventTypes::TEXT_MESSAGE_START, $decoded['type']);
        $this->assertEquals('facade-test', $decoded['messageid']);
    }

    public function testMediaType(): void
    {
        $this->assertEquals('application/vnd.ag-ui.event+proto', ProtoEncoder::AGUI_MEDIA_TYPE);
        $this->assertEquals('application/vnd.ag-ui.event+proto', Proto::AGUI_MEDIA_TYPE);
    }

    public function testInvalidEventType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $event = [
            'type' => 'INVALID_EVENT_TYPE',
            'data' => 'test'
        ];

        $this->encoder->encode($event);
    }

    public function testEventTypeMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event must have a type field');
        
        $event = [
            'data' => 'test'
        ];

        $this->encoder->encode($event);
    }

    public function testInvalidProtobufData(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $this->encoder->decode('invalid-protobuf-data');
    }
}

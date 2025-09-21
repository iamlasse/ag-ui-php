<?php

declare(strict_types=1);

namespace AGUI\Integrations\LangGraph\Tests;

use AGUI\Integrations\LangGraph\EventTranslator;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStartedEvent;
use AGUI\Core\Events\RunFinishedEvent;
use AGUI\Core\Events\RunErrorEvent;
use AGUI\Core\Events\TextMessageStartEvent;
use AGUI\Core\Events\TextMessageContentEvent;
use AGUI\Core\Events\TextMessageEndEvent;
use AGUI\Core\Events\ToolCallStartEvent;
use AGUI\Core\Events\ToolCallEndEvent;
use AGUI\Core\Events\StateSnapshotEvent;
use AGUI\Core\Events\StateDeltaEvent;
use AGUI\Core\Events\MessagesSnapshotEvent;
use AGUI\Core\Events\StepStartedEvent;
use AGUI\Core\Events\StepFinishedEvent;
use PHPUnit\Framework\TestCase;

/**
 * EventTranslator Test Suite
 *
 * @package AGUI\Integrations\LangGraph\Tests
 */
class EventTranslatorTest extends TestCase
{
    private EventTranslator $translator;
    private string $threadId;
    private string $runId;

    protected function setUp(): void
    {
        $this->translator = new EventTranslator();
        $this->threadId = 'test-thread-' . uniqid();
        $this->runId = 'test-run-' . uniqid();
    }

    public function testTranslateRunStartEvent(): void
    {
        $langGraphEvent = [
            'event' => 'run/start',
            'data' => [
                'thread_id' => $this->threadId,
                'run_id' => $this->runId,
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(RunStartedEvent::class, $result);
        $this->assertEquals($this->threadId, $result->threadId);
        $this->assertEquals($this->runId, $result->runId);
    }

    public function testTranslateRunEndEvent(): void
    {
        $langGraphEvent = [
            'event' => 'run/end',
            'data' => [
                'result' => ['status' => 'completed']
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(RunFinishedEvent::class, $result);
        $this->assertEquals($this->threadId, $result->threadId);
        $this->assertEquals($this->runId, $result->runId);
    }

    public function testTranslateRunErrorEvent(): void
    {
        $langGraphEvent = [
            'event' => 'run/error',
            'data' => [
                'error' => 'Test error message'
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(RunErrorEvent::class, $result);
        $this->assertEquals($this->threadId, $result->threadId);
        $this->assertEquals($this->runId, $result->runId);
        $this->assertEquals('Test error message', $result->error);
    }

    public function testTranslateMessagePartialEvent(): void
    {
        $langGraphEvent = [
            'event' => 'messages/partial',
            'data' => [
                'content' => 'Hello',
                'message_id' => 'msg-123',
                'role' => 'assistant'
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(TextMessageStartEvent::class, $result);
        $this->assertEquals('msg-123', $result->messageId);
        $this->assertEquals('assistant', $result->role);

        // Test content continuation
        $langGraphEvent2 = [
            'event' => 'messages/partial',
            'data' => [
                'content' => ' world!',
                'message_id' => 'msg-123'
            ]
        ];

        $result2 = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(TextMessageContentEvent::class, $result2);
        $this->assertEquals('msg-123', $result2->messageId);
        $this->assertEquals(' world!', $result2->delta);
    }

    public function testTranslateMessageCompleteEvent(): void
    {
        // First, create an active message
        $langGraphEvent1 = [
            'event' => 'messages/partial',
            'data' => [
                'content' => 'Hello',
                'message_id' => 'msg-123'
            ]
        ];

        $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);

        // Then complete it
        $langGraphEvent2 = [
            'event' => 'messages/complete',
            'data' => [
                'message_id' => 'msg-123'
            ]
        ];

        $result = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(TextMessageEndEvent::class, $result);
        $this->assertEquals('msg-123', $result->messageId);
    }

    public function testTranslateToolCallStartEvent(): void
    {
        $langGraphEvent = [
            'event' => 'tool_calls/start',
            'data' => [
                'tool_calls' => [
                    [
                        'id' => 'tool-123',
                        'function' => [
                            'name' => 'get_weather'
                        ]
                    ]
                ],
                'message_id' => 'msg-456'
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(ToolCallStartEvent::class, $result);
        $this->assertEquals('tool-123', $result->toolCallId);
        $this->assertEquals('get_weather', $result->toolCallName);
        $this->assertEquals('msg-456', $result->parentMessageId);
    }

    public function testTranslateToolCallEndEvent(): void
    {
        // First, create an active tool call
        $langGraphEvent1 = [
            'event' => 'tool_calls/start',
            'data' => [
                'tool_calls' => [
                    [
                        'id' => 'tool-123',
                        'function' => [
                            'name' => 'get_weather'
                        ]
                    ]
                ]
            ]
        ];

        $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);

        // Then end it
        $langGraphEvent2 = [
            'event' => 'tool_calls/end',
            'data' => [
                'tool_calls' => [
                    [
                        'id' => 'tool-123'
                    ]
                ]
            ]
        ];

        $result = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(ToolCallEndEvent::class, $result);
        $this->assertEquals('tool-123', $result->toolCallId);
    }

    public function testTranslateStateUpdateEvent(): void
    {
        // Test state snapshot
        $langGraphEvent1 = [
            'event' => 'state/update',
            'data' => [
                'state' => [
                    'snapshot' => ['key' => 'value']
                ]
            ]
        ];

        $result1 = $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);

        $this->assertInstanceOf(StateSnapshotEvent::class, $result1);
        $this->assertEquals(['key' => 'value'], $result1->state);

        // Test state delta
        $langGraphEvent2 = [
            'event' => 'state/update',
            'data' => [
                'state' => [
                    'delta' => [['op' => 'add', 'path' => '/new', 'value' => 'data']]
                ]
            ]
        ];

        $result2 = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(StateDeltaEvent::class, $result2);
        $this->assertEquals([['op' => 'add', 'path' => '/new', 'value' => 'data']], $result2->delta);

        // Test messages snapshot
        $langGraphEvent3 = [
            'event' => 'state/update',
            'data' => [
                'state' => [
                    'messages' => [
                        [
                            'id' => 'msg-789',
                            'type' => 'assistant',
                            'content' => 'Hello from state!'
                        ]
                    ]
                ]
            ]
        ];

        $result3 = $this->translator->translate($langGraphEvent3, $this->threadId, $this->runId);

        $this->assertInstanceOf(MessagesSnapshotEvent::class, $result3);
        $this->assertCount(1, $result3->messages);
        $this->assertEquals('Hello from state!', $result3->messages[0]->content);
    }

    public function testTranslateStepEvents(): void
    {
        $langGraphEvent1 = [
            'event' => 'step/start',
            'data' => [
                'step' => [
                    'id' => 'step-123',
                    'name' => 'process_input'
                ]
            ]
        ];

        $result1 = $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);

        $this->assertInstanceOf(StepStartedEvent::class, $result1);
        $this->assertEquals('step-123', $result1->stepId);
        $this->assertEquals('process_input', $result1->stepName);

        $langGraphEvent2 = [
            'event' => 'step/end',
            'data' => [
                'step' => [
                    'id' => 'step-123'
                ]
            ]
        ];

        $result2 = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(StepFinishedEvent::class, $result2);
        $this->assertEquals('step-123', $result2->stepId);
    }

    public function testTranslateThinkingEvents(): void
    {
        $langGraphEvent1 = [
            'event' => 'thinking/start',
            'data' => [
                'message' => 'Thinking about the problem...'
            ]
        ];

        $result1 = $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);

        $this->assertInstanceOf(\AGUI\Core\Events\BaseEvent::class, $result1);
        $this->assertEquals(EventType::CUSTOM, $result1->type);
        $this->assertEquals('thinking_start', $result1->rawEvent['translated_type']);

        $langGraphEvent2 = [
            'event' => 'thinking/end',
            'data' => []
        ];

        $result2 = $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $this->assertInstanceOf(\AGUI\Core\Events\BaseEvent::class, $result2);
        $this->assertEquals(EventType::CUSTOM, $result2->type);
        $this->assertEquals('thinking_end', $result2->rawEvent['translated_type']);
    }

    public function testTranslateCustomEvent(): void
    {
        $langGraphEvent = [
            'event' => 'custom/user_event',
            'data' => [
                'custom_data' => 'custom_value'
            ]
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(\AGUI\Core\Events\BaseEvent::class, $result);
        $this->assertEquals(EventType::CUSTOM, $result->type);
        $this->assertEquals($this->threadId, $result->rawEvent['thread_id']);
        $this->assertEquals($this->runId, $result->rawEvent['run_id']);
    }

    public function testTranslateInvalidEvent(): void
    {
        $langGraphEvent = [
            'event' => '',
            'data' => []
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        $this->assertInstanceOf(\AGUI\Core\Events\BaseEvent::class, $result);
        $this->assertEquals(EventType::CUSTOM, $result->type);
    }

    public function testTranslateMalformedEvent(): void
    {
        $langGraphEvent = [
            'event' => 'messages/partial',
            'data' => []
            // Missing required content field
        ];

        $result = $this->translator->translate($langGraphEvent, $this->threadId, $this->runId);

        // Should return null for malformed events
        $this->assertNull($result);
    }

    public function testActiveEventsManagement(): void
    {
        // Create active messages and tool calls
        $langGraphEvent1 = [
            'event' => 'messages/partial',
            'data' => [
                'content' => 'Hello',
                'message_id' => 'msg-123'
            ]
        ];

        $langGraphEvent2 = [
            'event' => 'tool_calls/start',
            'data' => [
                'tool_calls' => [
                    [
                        'id' => 'tool-123',
                        'function' => [
                            'name' => 'get_weather'
                        ]
                    ]
                ]
            ]
        ];

        $this->translator->translate($langGraphEvent1, $this->threadId, $this->runId);
        $this->translator->translate($langGraphEvent2, $this->threadId, $this->runId);

        $activeTextMessages = $this->translator->getActiveTextMessages();
        $activeToolCalls = $this->translator->getActiveToolCalls();

        $this->assertArrayHasKey('msg-123', $activeTextMessages);
        $this->assertArrayHasKey('tool-123', $activeToolCalls);
        $this->assertTrue($activeTextMessages['msg-123']);
        $this->assertTrue($activeToolCalls['tool-123']);

        // Clear active events
        $this->translator->clearActiveEvents();

        $activeTextMessages = $this->translator->getActiveTextMessages();
        $activeToolCalls = $this->translator->getActiveToolCalls();

        $this->assertEmpty($activeTextMessages);
        $this->assertEmpty($activeToolCalls);
    }

    public function testMessageConversionFromLangGraph(): void
    {
        $reflection = new \ReflectionClass($this->translator);
        $method = $reflection->getMethod('convertMessagesFromLangGraph');
        $method->setAccessible(true);

        $langGraphMessages = [
            [
                'id' => 'msg-123',
                'type' => 'assistant',
                'content' => 'Hello from LangGraph!',
                'name' => 'test-assistant',
                'tool_calls' => [
                    [
                        'id' => 'tool-456',
                        'function' => [
                            'name' => 'test_function',
                            'arguments' => '{"param": "value"}'
                        ]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($this->translator, $langGraphMessages);

        $this->assertCount(1, $result);
        $this->assertEquals('msg-123', $result[0]->id);
        $this->assertEquals('assistant', $result[0]->role);
        $this->assertEquals('Hello from LangGraph!', $result[0]->content);
        $this->assertEquals('test-assistant', $result[0]->name);
        $this->assertNotNull($result[0]->toolCalls);
        $this->assertEquals('tool-456', $result[0]->toolCalls[0]->id);
        $this->assertEquals('test_function', $result[0]->toolCalls[0]->function->name);
    }
}
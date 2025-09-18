<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventType;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for EventType enum
 *
 * @package AGUI\Core\Tests\Events
 */
class EventTypeTest extends TestCase
{
    /**
     * Test that all event types are valid
     */
    public function testAllEventTypesAreValid(): void
    {
        $allTypes = EventType::all();

        $this->assertIsArray($allTypes);
        $this->assertNotEmpty($allTypes);

        // Check that all expected types are present
        $expectedTypes = [
            'run_started',
            'run_finished',
            'text_message_start',
            'text_message_chunk',
            'text_message_end',
            'tool_call_start',
            'tool_call_chunk',
            'tool_call_end',
            'state_snapshot',
            'state_delta',
            'messages_snapshot'
        ];

        foreach ($expectedTypes as $expectedType) {
            $this->assertContains($expectedType, $allTypes);
        }
    }

    /**
     * Test event type validation
     */
    public function testEventTypeValidation(): void
    {
        $this->assertTrue(EventType::isValid('run_started'));
        $this->assertTrue(EventType::isValid('text_message_chunk'));
        $this->assertFalse(EventType::isValid('invalid_type'));
        $this->assertFalse(EventType::isValid(''));
    }

    /**
     * Test lifecycle event detection
     */
    public function testLifecycleEventDetection(): void
    {
        $this->assertTrue(EventType::RUN_STARTED->isLifecycleEvent());
        $this->assertTrue(EventType::RUN_FINISHED->isLifecycleEvent());
        $this->assertFalse(EventType::TEXT_MESSAGE_START->isLifecycleEvent());
        $this->assertFalse(EventType::TOOL_CALL_START->isLifecycleEvent());
        $this->assertFalse(EventType::STATE_SNAPSHOT->isLifecycleEvent());
    }

    /**
     * Test text message event detection
     */
    public function testTextMessageEventDetection(): void
    {
        $this->assertTrue(EventType::TEXT_MESSAGE_START->isTextMessageEvent());
        $this->assertTrue(EventType::TEXT_MESSAGE_CHUNK->isTextMessageEvent());
        $this->assertTrue(EventType::TEXT_MESSAGE_END->isTextMessageEvent());
        $this->assertFalse(EventType::RUN_STARTED->isTextMessageEvent());
        $this->assertFalse(EventType::TOOL_CALL_START->isTextMessageEvent());
    }

    /**
     * Test tool call event detection
     */
    public function testToolCallEventDetection(): void
    {
        $this->assertTrue(EventType::TOOL_CALL_START->isToolCallEvent());
        $this->assertTrue(EventType::TOOL_CALL_CHUNK->isToolCallEvent());
        $this->assertTrue(EventType::TOOL_CALL_END->isToolCallEvent());
        $this->assertFalse(EventType::RUN_STARTED->isToolCallEvent());
        $this->assertFalse(EventType::TEXT_MESSAGE_START->isToolCallEvent());
    }

    /**
     * Test state event detection
     */
    public function testStateEventDetection(): void
    {
        $this->assertTrue(EventType::STATE_SNAPSHOT->isStateEvent());
        $this->assertTrue(EventType::STATE_DELTA->isStateEvent());
        $this->assertTrue(EventType::MESSAGES_SNAPSHOT->isStateEvent());
        $this->assertFalse(EventType::RUN_STARTED->isStateEvent());
        $this->assertFalse(EventType::TEXT_MESSAGE_START->isStateEvent());
    }

    /**
     * Test event category mapping
     */
    public function testEventCategoryMapping(): void
    {
        $this->assertEquals('lifecycle', EventType::RUN_STARTED->getCategory());
        $this->assertEquals('lifecycle', EventType::RUN_FINISHED->getCategory());
        $this->assertEquals('text_message', EventType::TEXT_MESSAGE_START->getCategory());
        $this->assertEquals('text_message', EventType::TEXT_MESSAGE_END->getCategory());
        $this->assertEquals('tool_call', EventType::TOOL_CALL_START->getCategory());
        $this->assertEquals('tool_call', EventType::TOOL_CALL_END->getCategory());
        $this->assertEquals('state', EventType::STATE_SNAPSHOT->getCategory());
        $this->assertEquals('state', EventType::MESSAGES_SNAPSHOT->getCategory());
    }

    /**
     * Test enum values
     */
    public function testEnumValues(): void
    {
        $this->assertEquals('run_started', EventType::RUN_STARTED->value);
        $this->assertEquals('run_finished', EventType::RUN_FINISHED->value);
        $this->assertEquals('text_message_start', EventType::TEXT_MESSAGE_START->value);
        $this->assertEquals('text_message_chunk', EventType::TEXT_MESSAGE_CHUNK->value);
        $this->assertEquals('text_message_end', EventType::TEXT_MESSAGE_END->value);
        $this->assertEquals('tool_call_start', EventType::TOOL_CALL_START->value);
        $this->assertEquals('tool_call_chunk', EventType::TOOL_CALL_CHUNK->value);
        $this->assertEquals('tool_call_end', EventType::TOOL_CALL_END->value);
        $this->assertEquals('state_snapshot', EventType::STATE_SNAPSHOT->value);
        $this->assertEquals('state_delta', EventType::STATE_DELTA->value);
        $this->assertEquals('messages_snapshot', EventType::MESSAGES_SNAPSHOT->value);
    }
}
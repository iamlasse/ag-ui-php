<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\TextMessageStart;
use AGUI\Core\Events\TextMessageChunk;
use AGUI\Core\Events\TextMessageEnd;
use AGUI\Core\Types\UserMessage;
use AGUI\Core\Types\Role;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for text message events
 *
 * @package AGUI\Core\Tests\Events
 */
class TextMessageEventsTest extends TestCase
{
    /**
     * Test TextMessageStart creation
     */
    public function testTextMessageStartCreation(): void
    {
        $message = new UserMessage('test-id', 'Hello, world!');
        $event = EventFactory::createTextMessageStart($message);

        $this->assertInstanceOf(TextMessageStart::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_START, $event->getType());
        $this->assertEquals($message, $event->getMessage());
    }

    /**
     * Test TextMessageStart array conversion
     */
    public function testTextMessageStartToArray(): void
    {
        $message = new UserMessage('test-id', 'Hello, world!');
        $event = EventFactory::createTextMessageStart($message);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('text_message_start', $array['type']);
    }

    /**
     * Test TextMessageStart event data
     */
    public function testTextMessageStartEventData(): void
    {
        $message = new UserMessage('test-id', 'Hello, world!');
        $event = EventFactory::createTextMessageStart($message);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('message', $eventData);
        $this->assertEquals($message->toArray(), $eventData['message']);
    }

    /**
     * Test TextMessageStart withMessage method
     */
    public function testTextMessageStartWithMessage(): void
    {
        $message1 = new UserMessage('test-id-1', 'Hello');
        $message2 = new UserMessage('test-id-2', 'World');
        $event = EventFactory::createTextMessageStart($message1);
        $newEvent = $event->withMessage($message2);

        $this->assertEquals($message2, $newEvent->getMessage());
        $this->assertEquals($event->getId(), $newEvent->getId());
    }

    /**
     * Test TextMessageChunk creation
     */
    public function testTextMessageChunkCreation(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello', 0, false);

        $this->assertInstanceOf(TextMessageChunk::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_CHUNK, $event->getType());
        $this->assertEquals('msg-id', $event->getMessageId());
        $this->assertEquals('Hello', $event->getContent());
        $this->assertEquals(0, $event->getChunkIndex());
        $this->assertFalse($event->isLast());
    }

    /**
     * Test TextMessageChunk with optional parameters
     */
    public function testTextMessageChunkWithOptionalParameters(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello');

        $this->assertEquals('msg-id', $event->getMessageId());
        $this->assertEquals('Hello', $event->getContent());
        $this->assertNull($event->getChunkIndex());
        $this->assertNull($event->isLast());
    }

    /**
     * Test TextMessageChunk array conversion
     */
    public function testTextMessageChunkToArray(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello', 0, true);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('text_message_chunk', $array['type']);
    }

    /**
     * Test TextMessageChunk event data
     */
    public function testTextMessageChunkEventData(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello', 0, true);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('messageId', $eventData);
        $this->assertArrayHasKey('content', $eventData);
        $this->assertArrayHasKey('chunkIndex', $eventData);
        $this->assertArrayHasKey('isLast', $eventData);
        $this->assertEquals('msg-id', $eventData['messageId']);
        $this->assertEquals('Hello', $eventData['content']);
        $this->assertEquals(0, $eventData['chunkIndex']);
        $this->assertTrue($eventData['isLast']);
    }

    /**
     * Test TextMessageChunk withContent method
     */
    public function testTextMessageChunkWithContent(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello');
        $newEvent = $event->withContent('World');

        $this->assertEquals('World', $newEvent->getContent());
        $this->assertEquals($event->getMessageId(), $newEvent->getMessageId());
    }

    /**
     * Test TextMessageChunk withChunkIndex method
     */
    public function testTextMessageChunkWithChunkIndex(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello');
        $newEvent = $event->withChunkIndex(5);

        $this->assertEquals(5, $newEvent->getChunkIndex());
        $this->assertEquals($event->getContent(), $newEvent->getContent());
    }

    /**
     * Test TextMessageChunk withIsLast method
     */
    public function testTextMessageChunkWithIsLast(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'Hello');
        $newEvent = $event->withIsLast(true);

        $this->assertTrue($newEvent->isLast());
        $this->assertEquals($event->getContent(), $newEvent->getContent());
    }

    /**
     * Test TextMessageEnd creation
     */
    public function testTextMessageEndCreation(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id', 'Final content', 10);

        $this->assertInstanceOf(TextMessageEnd::class, $event);
        $this->assertEquals(EventType::TEXT_MESSAGE_END, $event->getType());
        $this->assertEquals('msg-id', $event->getMessageId());
        $this->assertEquals('Final content', $event->getFinalContent());
        $this->assertEquals(10, $event->getTotalChunks());
    }

    /**
     * Test TextMessageEnd with optional parameters
     */
    public function testTextMessageEndWithOptionalParameters(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id');

        $this->assertEquals('msg-id', $event->getMessageId());
        $this->assertNull($event->getFinalContent());
        $this->assertNull($event->getTotalChunks());
    }

    /**
     * Test TextMessageEnd array conversion
     */
    public function testTextMessageEndToArray(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id', 'Final content', 10);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('text_message_end', $array['type']);
    }

    /**
     * Test TextMessageEnd event data
     */
    public function testTextMessageEndEventData(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id', 'Final content', 10);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('messageId', $eventData);
        $this->assertArrayHasKey('finalContent', $eventData);
        $this->assertArrayHasKey('totalChunks', $eventData);
        $this->assertEquals('msg-id', $eventData['messageId']);
        $this->assertEquals('Final content', $eventData['finalContent']);
        $this->assertEquals(10, $eventData['totalChunks']);
    }

    /**
     * Test TextMessageEnd withFinalContent method
     */
    public function testTextMessageEndWithFinalContent(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id');
        $newEvent = $event->withFinalContent('New content');

        $this->assertEquals('New content', $newEvent->getFinalContent());
        $this->assertEquals($event->getMessageId(), $newEvent->getMessageId());
    }

    /**
     * Test TextMessageEnd withTotalChunks method
     */
    public function testTextMessageEndWithTotalChunks(): void
    {
        $event = EventFactory::createTextMessageEnd('msg-id');
        $newEvent = $event->withTotalChunks(25);

        $this->assertEquals(25, $newEvent->getTotalChunks());
        $this->assertEquals($event->getMessageId(), $newEvent->getMessageId());
    }

    /**
     * Test validation for empty message ID
     */
    public function testValidationThrowsForEmptyMessageId(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createTextMessageChunk('', 'content');
    }

    /**
     * Test validation for negative chunk index
     */
    public function testValidationAllowsNullChunkIndex(): void
    {
        $event = EventFactory::createTextMessageChunk('msg-id', 'content', null);
        $this->assertNull($event->getChunkIndex());
    }
}
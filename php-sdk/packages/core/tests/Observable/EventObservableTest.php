<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Observable;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Observable\StreamProcessor;
use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Events\RunFinished;
use AGUI\Core\Events\TextMessageStart;
use AGUI\Core\Events\TextMessageChunk;
use AGUI\Core\Events\TextMessageEnd;
use AGUI\Core\Events\ToolCallStart;
use AGUI\Core\Events\ToolCallEnd;
use AGUI\Core\Events\StateSnapshot;
use AGUI\Core\Events\StateDelta;
use PHPUnit\Framework\TestCase;

/**
 * Test case for EventObservable class
 *
 * @package AGUI\Tests\Core\Observable
 */
class EventObservableTest extends TestCase
{
    private EventObservable $observable;

    protected function setUp(): void
    {
        $this->observable = new EventObservable();
    }

    public function testCreateEventObservable(): void
    {
        $this->assertInstanceOf(EventObservable::class, $this->observable);
    }

    public function testEmitAndSubscribeToEvents(): void
    {
        $event = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $receivedEvents = [];

        $subscription = $this->observable
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        $this->observable->emitEvent($event);

        $this->assertCount(1, $receivedEvents);
        $this->assertEquals($event, $receivedEvents[0]);
    }

    public function testFilterByEventType(): void
    {
        $runEvent = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $messageEvent = new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START);
        $receivedEvents = [];

        $filtered = $this->observable
            ->filterByEventType(EventType::RUN_STARTED)
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        $this->observable->emitEvent($runEvent);
        $this->observable->emitEvent($messageEvent);

        $this->assertCount(1, $receivedEvents);
        $this->assertEquals($runEvent, $receivedEvents[0]);
    }

    public function testFilterByCategory(): void
    {
        $runEvent = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $messageEvent = new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START);
        $receivedEvents = [];

        $filtered = $this->observable
            ->filterByCategory('lifecycle')
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        $this->observable->emitEvent($runEvent);
        $this->observable->emitEvent($messageEvent);

        $this->assertCount(1, $receivedEvents);
        $this->assertEquals($runEvent, $receivedEvents[0]);
    }

    public function testFilterByRunId(): void
    {
        $event1 = new RunStarted('test-run-1', EventType::RUN_STARTED, 'run-1');
        $event2 = new RunStarted('test-run-2', EventType::RUN_STARTED, 'run-2');
        $receivedEvents = [];

        $filtered = $this->observable
            ->filterByRunId('run-1')
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        $this->observable->emitEvent($event1);
        $this->observable->emitEvent($event2);

        $this->assertCount(1, $receivedEvents);
        $this->assertEquals($event1, $receivedEvents[0]);
    }

    public function testMapToEventData(): void
    {
        $event = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $receivedData = [];

        $mapped = $this->observable
            ->mapToEventData()
            ->subscribeEvents(
                function ($data) use (&$receivedData) {
                    $receivedData[] = $data;
                }
            );

        $this->observable->emitEvent($event);

        $this->assertCount(1, $receivedData);
        $this->assertEquals($event->getEventData(), $receivedData[0]);
    }

    public function testMapToFullData(): void
    {
        $event = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $receivedData = [];

        $mapped = $this->observable
            ->mapToFullData()
            ->subscribeEvents(
                function ($data) use (&$receivedData) {
                    $receivedData[] = $data;
                }
            );

        $this->observable->emitEvent($event);

        $this->assertCount(1, $receivedData);
        $this->assertEquals($event->getFullData(), $receivedData[0]);
    }

    public function testMapToJson(): void
    {
        $event = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $receivedJson = [];

        $mapped = $this->observable
            ->mapToJson()
            ->subscribeEvents(
                function ($json) use (&$receivedJson) {
                    $receivedJson[] = $json;
                }
            );

        $this->observable->emitEvent($event);

        $this->assertCount(1, $receivedJson);
        $this->assertEquals($event->toJson(), $receivedJson[0]);
    }

    public function testTakeEvents(): void
    {
        $events = [
            new RunStarted('test-run-1', EventType::RUN_STARTED),
            new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START),
            new RunFinished('test-run-2', EventType::RUN_FINISHED)
        ];
        $receivedEvents = [];

        $limited = $this->observable
            ->takeEvents(2)
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        $this->assertCount(2, $receivedEvents);
        $this->assertEquals($events[0], $receivedEvents[0]);
        $this->assertEquals($events[1], $receivedEvents[1]);
    }

    public function testSkipEvents(): void
    {
        $events = [
            new RunStarted('test-run-1', EventType::RUN_STARTED),
            new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START),
            new RunFinished('test-run-2', EventType::RUN_FINISHED)
        ];
        $receivedEvents = [];

        $skipped = $this->observable
            ->skipEvents(1)
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        $this->assertCount(2, $receivedEvents);
        $this->assertEquals($events[1], $receivedEvents[0]);
        $this->assertEquals($events[2], $receivedEvents[1]);
    }

    public function testDistinctEvents(): void
    {
        $event1 = new RunStarted('test-run-1', EventType::RUN_STARTED);
        $event2 = new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START);
        $event3 = new RunStarted('test-run-1', EventType::RUN_STARTED); // Duplicate ID
        $receivedEvents = [];

        $distinct = $this->observable
            ->distinctEvents()
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                }
            );

        $this->observable->emitEvent($event1);
        $this->observable->emitEvent($event2);
        $this->observable->emitEvent($event3);

        $this->assertCount(2, $receivedEvents);
        $this->assertEquals($event1, $receivedEvents[0]);
        $this->assertEquals($event2, $receivedEvents[1]);
    }

    public function testCountEvents(): void
    {
        $events = [
            new RunStarted('test-run-1', EventType::RUN_STARTED),
            new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START),
            new RunFinished('test-run-2', EventType::RUN_FINISHED)
        ];
        $receivedCounts = [];

        $counted = $this->observable
            ->countEvents()
            ->subscribeEvents(
                function ($count) use (&$receivedCounts) {
                    $receivedCounts[] = $count;
                }
            );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        // Complete the stream to get the count
        $this->observable->complete();

        $this->assertCount(1, $receivedCounts);
        $this->assertEquals(3, $receivedCounts[0]);
    }

    public function testFromEvents(): void
    {
        $events = [
            new RunStarted('test-run-1', EventType::RUN_STARTED),
            new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START),
            new RunFinished('test-run-2', EventType::RUN_FINISHED)
        ];
        $receivedEvents = [];

        $observable = EventObservable::fromEvents($events);
        $observable->subscribeEvents(
            function (BaseEvent $event) use (&$receivedEvents) {
                $receivedEvents[] = $event;
            }
        );

        $this->assertCount(3, $receivedEvents);
        $this->assertEquals($events, $receivedEvents);
    }

    public function testFromGenerator(): void
    {
        $generator = function () {
            yield new RunStarted('test-run-1', EventType::RUN_STARTED);
            yield new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START);
            yield new RunFinished('test-run-2', EventType::RUN_FINISHED);
        };
        $receivedEvents = [];

        $observable = EventObservable::fromGenerator($generator);
        $observable->subscribeEvents(
            function (BaseEvent $event) use (&$receivedEvents) {
                $receivedEvents[] = $event;
            }
        );

        $this->assertCount(3, $receivedEvents);
    }

    public function testErrorHandling(): void
    {
        $receivedErrors = [];
        $receivedEvents = [];

        $subscription = $this->observable
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                },
                function ($error) use (&$receivedErrors) {
                    $receivedErrors[] = $error;
                }
            );

        $this->observable->emitEvent(new RunStarted('test-run-1', EventType::RUN_STARTED));
        $this->observable->emitError(new \RuntimeException('Test error'));
        $this->observable->emitEvent(new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START));

        $this->assertCount(1, $receivedEvents);
        $this->assertCount(1, $receivedErrors);
        $this->assertInstanceOf(\RuntimeException::class, $receivedErrors[0]);
    }

    public function testCompletion(): void
    {
        $receivedEvents = [];
        $completed = false;

        $subscription = $this->observable
            ->subscribeEvents(
                function (BaseEvent $event) use (&$receivedEvents) {
                    $receivedEvents[] = $event;
                },
                null,
                function () use (&$completed) {
                    $completed = true;
                }
            );

        $this->observable->emitEvent(new RunStarted('test-run-1', EventType::RUN_STARTED));
        $this->observable->complete();

        $this->assertCount(1, $receivedEvents);
        $this->assertTrue($completed);
    }
}
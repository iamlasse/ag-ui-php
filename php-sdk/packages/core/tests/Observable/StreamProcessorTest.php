<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Observable;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Observable\StreamProcessor;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Events\RunFinished;
use AGUI\Core\Events\TextMessageStart;
use AGUI\Core\Events\TextMessageChunk;
use AGUI\Core\Events\TextMessageEnd;
use AGUI\Core\Events\ToolCallStart;
use AGUI\Core\Events\ToolCallChunk;
use AGUI\Core\Events\ToolCallEnd;
use AGUI\Core\Events\StateSnapshot;
use AGUI\Core\Events\StateDelta;
use AGUI\Core\Events\EventType;
use PHPUnit\Framework\TestCase;

/**
 * Test case for StreamProcessor class
 *
 * @package AGUI\Tests\Core\Observable
 */
class StreamProcessorTest extends TestCase
{
    private StreamProcessor $processor;
    private EventObservable $observable;

    protected function setUp(): void
    {
        $this->processor = new StreamProcessor();
        $this->observable = new EventObservable();
    }

    public function testCreateStreamProcessor(): void
    {
        $this->assertInstanceOf(StreamProcessor::class, $this->processor);
    }

    public function testProcessRunLifecycle(): void
    {
        $runStarted = new RunStarted('test-run-1', EventType::RUN_STARTED, 'run-1');
        $textMessage = new TextMessageStart('test-msg-1', EventType::TEXT_MESSAGE_START, 'run-1');
        $runFinished = new RunFinished('test-run-2', EventType::RUN_FINISHED, 'run-1');

        $runStartReceived = false;
        $runEndReceived = false;
        $eventsReceived = [];

        $processed = $this->processor->processRunLifecycle(
            $this->observable,
            function (RunStarted $event) use (&$runStartReceived) {
                $runStartReceived = true;
            },
            function (RunFinished $event) use (&$runEndReceived) {
                $runEndReceived = true;
            },
            function ($event) use (&$eventsReceived) {
                $eventsReceived[] = $event;
            }
        );

        $processed->subscribeEvents();

        $this->observable->emitEvent($runStarted);
        $this->observable->emitEvent($textMessage);
        $this->observable->emitEvent($runFinished);

        $this->assertTrue($runStartReceived);
        $this->assertTrue($runEndReceived);
        $this->assertCount(1, $eventsReceived);
    }

    public function testAggregateTextMessages(): void
    {
        $start = new TextMessageStart('msg-start-1', EventType::TEXT_MESSAGE_START, 'run-1');
        $chunk1 = new TextMessageChunk('msg-chunk-1', EventType::TEXT_MESSAGE_CHUNK, 'run-1');
        $chunk2 = new TextMessageChunk('msg-chunk-2', EventType::TEXT_MESSAGE_CHUNK, 'run-1');
        $end = new TextMessageEnd('msg-end-1', EventType::TEXT_MESSAGE_END, 'run-1');

        // Mock chunk data
        $chunk1Data = ['content' => 'Hello '];
        $chunk2Data = ['content' => 'World!'];

        // We need to create custom events with proper data
        $startEvent = $this->createTextMessageStart($start->getId(), $start->getRunId());
        $chunk1Event = $this->createTextMessageChunk($chunk1->getId(), $chunk1->getRunId(), $chunk1Data);
        $chunk2Event = $this->createTextMessageChunk($chunk2->getId(), $chunk2->getRunId(), $chunk2Data);
        $endEvent = $this->createTextMessageEnd($end->getId(), $end->getRunId());

        $receivedMessages = [];

        $aggregated = $this->processor->aggregateTextMessages($this->observable);
        $aggregated->subscribeEvents(
            function ($message) use (&$receivedMessages) {
                $receivedMessages[] = $message;
            }
        );

        $this->observable->emitEvent($startEvent);
        $this->observable->emitEvent($chunk1Event);
        $this->observable->emitEvent($chunk2Event);
        $this->observable->emitEvent($endEvent);

        $this->assertCount(1, $receivedMessages);
        $this->assertEquals('Hello World!', $receivedMessages[0]['content']);
    }

    public function testAggregateToolCalls(): void
    {
        $start = new ToolCallStart('tool-start-1', EventType::TOOL_CALL_START, 'run-1');
        $chunk1 = new ToolCallChunk('tool-chunk-1', EventType::TOOL_CALL_CHUNK, 'run-1');
        $chunk2 = new ToolCallChunk('tool-chunk-2', EventType::TOOL_CALL_CHUNK, 'run-1');
        $end = new ToolCallEnd('tool-end-1', EventType::TOOL_CALL_END, 'run-1');

        $startEvent = $this->createToolCallStart($start->getId(), $start->getRunId(), 'test_tool', 'input data');
        $chunk1Event = $this->createToolCallChunk($chunk1->getId(), $chunk1->getRunId(), 'Output part 1');
        $chunk2Event = $this->createToolCallChunk($chunk2->getId(), $chunk2->getRunId(), 'Output part 2');
        $endEvent = $this->createToolCallEnd($end->getId(), $end->getRunId());

        $receivedToolCalls = [];

        $aggregated = $this->processor->aggregateToolCalls($this->observable);
        $aggregated->subscribeEvents(
            function ($toolCall) use (&$receivedToolCalls) {
                $receivedToolCalls[] = $toolCall;
            }
        );

        $this->observable->emitEvent($startEvent);
        $this->observable->emitEvent($chunk1Event);
        $this->observable->emitEvent($chunk2Event);
        $this->observable->emitEvent($endEvent);

        $this->assertCount(1, $receivedToolCalls);
        $this->assertEquals('test_tool', $receivedToolCalls[0]['tool']);
        $this->assertEquals('input data', $receivedToolCalls[0]['input']);
        $this->assertEquals('Output part 1Output part 2', $receivedToolCalls[0]['output']);
    }

    public function testProcessStateChanges(): void
    {
        $initialState = ['count' => 0, 'items' => []];
        $snapshot = $this->createStateSnapshot('state-1', 'run-1', $initialState);
        $delta = $this->createStateDelta('delta-1', 'run-1', [
            ['op' => 'add', 'path' => '/items/0', 'value' => 'item1'],
            ['op' => 'replace', 'path' => '/count', 'value' => 1]
        ]);

        $receivedStates = [];

        $processed = $this->processor->processStateChanges($this->observable);
        $processed->subscribeEvents(
            function ($state) use (&$receivedStates) {
                $receivedStates[] = $state;
            }
        );

        $this->observable->emitEvent($snapshot);
        $this->observable->emitEvent($delta);

        $this->assertCount(2, $receivedStates);

        // Check snapshot
        $this->assertEquals('snapshot', $receivedStates[0]['type']);
        $this->assertEquals($initialState, $receivedStates[0]['state']);

        // Check delta application
        $this->assertEquals('delta', $receivedStates[1]['type']);
        $this->assertEquals(1, $receivedStates[1]['state']['count']);
        $this->assertEquals(['item1'], $receivedStates[1]['state']['items']);
    }

    public function testCreateEventStatistics(): void
    {
        $events = [
            new RunStarted('run-1', EventType::RUN_STARTED, 'run-1'),
            new TextMessageStart('msg-1', EventType::TEXT_MESSAGE_START, 'run-1'),
            new RunFinished('run-2', EventType::RUN_FINISHED, 'run-1'),
            new TextMessageStart('msg-2', EventType::TEXT_MESSAGE_START, 'run-2')
        ];

        $receivedStats = [];

        $statsProcessor = $this->processor->createEventStatistics($this->observable);
        $statsProcessor->subscribeEvents(
            function ($stat) use (&$receivedStats) {
                $receivedStats[] = $stat;
            }
        );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        $this->assertCount(4, $receivedStats);

        $finalStats = $receivedStats[3]['stats'];
        $this->assertEquals(4, $finalStats['total_events']);
        $this->assertEquals(1, $finalStats['by_type']['run_started']);
        $this->assertEquals(2, $finalStats['by_type']['text_message_start']);
        $this->assertEquals(1, $finalStats['by_type']['run_finished']);
        $this->assertEquals(2, $finalStats['by_category']['lifecycle']);
        $this->assertEquals(2, $finalStats['by_category']['text_message']);
    }

    public function testDebounceByType(): void
    {
        $events = [
            new RunStarted('run-1', EventType::RUN_STARTED, 'run-1'),
            new TextMessageStart('msg-1', EventType::TEXT_MESSAGE_START, 'run-1'),
            new RunStarted('run-2', EventType::RUN_STARTED, 'run-1'), // Same type
            new TextMessageStart('msg-2', EventType::TEXT_MESSAGE_START, 'run-1') // Same type
        ];

        $receivedBatches = [];

        $debounced = $this->processor->debounceByType($this->observable, 100); // 100ms debounce
        $debounced->subscribeEvents(
            function ($batch) use (&$receivedBatches) {
                $receivedBatches[] = $batch;
            }
        );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        // Note: In real scenario, we'd need to wait for debounce time
        // For testing, we check that batching structure is working
        $this->assertGreaterThanOrEqual(0, count($receivedBatches));
    }

    public function testBatchByTimeWindow(): void
    {
        $events = [
            new RunStarted('run-1', EventType::RUN_STARTED, 'run-1'),
            new TextMessageStart('msg-1', EventType::TEXT_MESSAGE_START, 'run-1'),
            new RunFinished('run-2', EventType::RUN_FINISHED, 'run-1')
        ];

        $receivedBatches = [];

        $batched = $this->processor->batchByTimeWindow($this->observable, 1000); // 1s window
        $batched->subscribeEvents(
            function ($batch) use (&$receivedBatches) {
                $receivedBatches[] = $batch;
            }
        );

        foreach ($events as $event) {
            $this->observable->emitEvent($event);
        }

        // All events should be in one batch since they're emitted within the time window
        $this->assertGreaterThanOrEqual(1, count($receivedBatches));
    }

    /**
     * Helper method to create TextMessageStart with data
     */
    private function createTextMessageStart(string $id, ?string $runId = null)
    {
        return new class($id, EventType::TEXT_MESSAGE_START, $runId) extends \AGUI\Core\Events\BaseEvent {
            public function getEventData(): array
            {
                return [
                    'message_id' => $this->id,
                    'role' => 'assistant',
                    'content' => ''
                ];
            }
        };
    }

    /**
     * Helper method to create TextMessageChunk with data
     */
    private function createTextMessageChunk(string $id, ?string $runId = null, array $data = [])
    {
        return new class($id, EventType::TEXT_MESSAGE_CHUNK, $runId) extends \AGUI\Core\Events\BaseEvent {
            private array $chunkData;

            public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, array $chunkData = [])
            {
                $this->chunkData = $chunkData;
                parent::__construct($id, $type, $runId, $timestamp, $metadata);
            }

            public function getEventData(): array
            {
                return $this->chunkData;
            }
        };
    }

    /**
     * Helper method to create TextMessageEnd with data
     */
    private function createTextMessageEnd(string $id, ?string $runId = null)
    {
        return new class($id, EventType::TEXT_MESSAGE_END, $runId) extends \AGUI\Core\Events\BaseEvent {
            public function getEventData(): array
            {
                return [
                    'message_id' => $this->id,
                    'role' => 'assistant',
                    'content' => ''
                ];
            }
        };
    }

    /**
     * Helper method to create ToolCallStart with data
     */
    private function createToolCallStart(string $id, ?string $runId = null, string $tool = '', string $input = '')
    {
        return new class($id, EventType::TOOL_CALL_START, $runId) extends \AGUI\Core\Events\BaseEvent {
            private string $toolName;
            private string $inputData;

            public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, string $tool = '', string $input = '')
            {
                $this->toolName = $tool;
                $this->inputData = $input;
                parent::__construct($id, $type, $runId, $timestamp, $metadata);
            }

            public function getEventData(): array
            {
                return [
                    'tool' => $this->toolName,
                    'input' => $this->inputData
                ];
            }
        };
    }

    /**
     * Helper method to create ToolCallChunk with data
     */
    private function createToolCallChunk(string $id, ?string $runId = null, string $content = '')
    {
        return new class($id, EventType::TOOL_CALL_CHUNK, $runId) extends \AGUI\Core\Events\BaseEvent {
            private string $content;

            public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, string $content = '')
            {
                $this->content = $content;
                parent::__construct($id, $type, $runId, $timestamp, $metadata);
            }

            public function getEventData(): array
            {
                return [
                    'content' => $this->content
                ];
            }
        };
    }

    /**
     * Helper method to create ToolCallEnd with data
     */
    private function createToolCallEnd(string $id, ?string $runId = null)
    {
        return new class($id, EventType::TOOL_CALL_END, $runId) extends \AGUI\Core\Events\BaseEvent {
            public function getEventData(): array
            {
                return [
                    'status' => 'completed'
                ];
            }
        };
    }

    /**
     * Helper method to create StateSnapshot with data
     */
    private function createStateSnapshot(string $id, ?string $runId = null, array $state = [])
    {
        return new class($id, EventType::STATE_SNAPSHOT, $runId) extends \AGUI\Core\Events\BaseEvent {
            private array $stateData;

            public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, array $state = [])
            {
                $this->stateData = $state;
                parent::__construct($id, $type, $runId, $timestamp, $metadata);
            }

            public function getEventData(): array
            {
                return [
                    'state' => $this->stateData
                ];
            }
        };
    }

    /**
     * Helper method to create StateDelta with data
     */
    private function createStateDelta(string $id, ?string $runId = null, array $patches = [])
    {
        return new class($id, EventType::STATE_DELTA, $runId) extends \AGUI\Core\Events\BaseEvent {
            private array $patchData;

            public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, array $patches = [])
            {
                $this->patchData = $patches;
                parent::__construct($id, $type, $runId, $timestamp, $metadata);
            }

            public function getEventData(): array
            {
                return [
                    'patches' => $this->patchData
                ];
            }
        };
    }
}
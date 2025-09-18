<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Observable\StreamProcessor;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Events\RunFinished;
use AGUI\Core\Events\TextMessageStart;
use AGUI\Core\Events\TextMessageChunk;
use AGUI\Core\Events\TextMessageEnd;

/**
 * RxPHP Observable Integration Example
 *
 * This example demonstrates how to use the EventObservable class with RxPHP
 * for streaming AG-UI events with reactive programming patterns.
 */

// Create an EventObservable instance
$observable = new EventObservable();

// Example 1: Basic event subscription
echo "=== Example 1: Basic Event Subscription ===\n";

$subscription1 = $observable
    ->filterByEventType(EventType::TEXT_MESSAGE_START)
    ->mapToEventData()
    ->subscribeEvents(
        function ($data) {
            echo "Text message started: " . json_encode($data) . "\n";
        }
    );

// Emit some events
$observable->emitEvent(new RunStarted('run-1', EventType::RUN_STARTED, 'example-run'));
$observable->emitEvent(new TextMessageStart('msg-1', EventType::TEXT_MESSAGE_START, 'example-run'));

// Example 2: Stream processing with aggregation
echo "\n=== Example 2: Message Aggregation ===\n";

$processor = new StreamProcessor();

// Create a new observable for message aggregation
$messageObservable = new EventObservable();

$aggregated = $processor->aggregateTextMessages($messageObservable);
$aggregated->subscribeEvents(
    function ($message) {
        echo "Complete message: " . $message['content'] . "\n";
    }
);

// Emit message chunks
$messageObservable->emitEvent(new TextMessageStart('msg-start-1', EventType::TEXT_MESSAGE_START, 'run-1'));
$messageObservable->emitEvent(createTextChunk('msg-chunk-1', 'run-1', 'Hello '));
$messageObservable->emitEvent(createTextChunk('msg-chunk-2', 'run-1', 'World!'));
$messageObservable->emitEvent(new TextMessageEnd('msg-end-1', EventType::TEXT_MESSAGE_END, 'run-1'));

// Example 3: Complex pipeline with multiple operators
echo "\n=== Example 3: Complex Pipeline ===\n";

$pipelineObservable = new EventObservable();

$complexPipeline = $pipelineObservable
    ->filterByCategory('text_message')
    ->mapToFullData()
    ->takeEvents(3)
    ->debounce(100); // 100ms debounce

$complexPipeline->subscribeEvents(
    function ($data) {
        echo "Processed event: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    },
    function ($error) {
        echo "Error: " . $error->getMessage() . "\n";
    },
    function () {
        echo "Pipeline completed\n";
    }
);

// Emit more events
$pipelineObservable->emitEvent(new TextMessageStart('msg-2', EventType::TEXT_MESSAGE_START, 'run-2'));
$pipelineObservable->emitEvent(new TextMessageStart('msg-3', EventType::TEXT_MESSAGE_START, 'run-2'));
$pipelineObservable->emitEvent(new TextMessageStart('msg-4', EventType::TEXT_MESSAGE_START, 'run-2'));
$pipelineObservable->emitEvent(new RunFinished('run-3', EventType::RUN_FINISHED, 'run-2'));

// Example 4: Run lifecycle processing
echo "\n=== Example 4: Run Lifecycle Processing ===\n";

$lifecycleObservable = new EventObservable();

$runStarts = 0;
$runEnds = 0;
$eventsProcessed = 0;

$processor->processRunLifecycle(
    $lifecycleObservable,
    function ($runStart) use (&$runStarts) {
        $runStarts++;
        echo "Run started: {$runStart->getId()}\n";
    },
    function ($runEnd) use (&$runEnds) {
        $runEnds++;
        echo "Run finished: {$runEnd->getId()}\n";
    },
    function ($event) use (&$eventsProcessed) {
        $eventsProcessed++;
        echo "Event processed: {$event->getType()->value}\n";
    }
)->subscribeEvents();

// Simulate a run lifecycle
$lifecycleObservable->emitEvent(new RunStarted('run-start-1', EventType::RUN_STARTED, 'lifecycle-run'));
$lifecycleObservable->emitEvent(new TextMessageStart('msg-5', EventType::TEXT_MESSAGE_START, 'lifecycle-run'));
$lifecycleObservable->emitEvent(new RunFinished('run-end-1', EventType::RUN_FINISHED, 'lifecycle-run'));

// Example 5: Event statistics
echo "\n=== Example 5: Event Statistics ===\n";

$statsObservable = new EventObservable();

$statsProcessor = $processor->createEventStatistics($statsObservable);
$statsProcessor->subscribeEvents(
    function ($stat) {
        $stats = $stat['stats'];
        echo "Total events: {$stats['total_events']}\n";
        echo "By type: " . json_encode($stats['by_type']) . "\n";
        echo "By category: " . json_encode($stats['by_category']) . "\n";
    }
);

// Emit events for statistics
$statsObservable->emitEvent(new RunStarted('stats-run-1', EventType::RUN_STARTED));
$statsObservable->emitEvent(new TextMessageStart('stats-msg-1', EventType::TEXT_MESSAGE_START));
$statsObservable->emitEvent(new RunFinished('stats-run-2', EventType::RUN_FINISHED));

// Example 6: Working with promises
echo "\n=== Example 6: Promise Integration ===\n";

$promiseObservable = new EventObservable();

$promise = new \React\Promise\Promise(function ($resolve, $reject) {
    // Simulate async operation
    \React\EventLoop\Loop::addTimer(1, function () use ($resolve) {
        $event = new TextMessageStart('promise-msg-1', EventType::TEXT_MESSAGE_START);
        $resolve($event);
    });
});

$promiseStream = EventObservable::fromPromise($promise);
$promiseStream->subscribeEvents(
    function ($event) {
        echo "Promise resolved with event: {$event->getId()}\n";
    }
);

// Example 7: Event transformation
echo "\n=== Example 7: Event Transformation ===\n";

$transformObservable = new EventObservable();

$transformed = $transformObservable
    ->map(function ($event) {
        return [
            'original_id' => $event->getId(),
            'type' => $event->getType()->value,
            'category' => $event->getType()->getCategory(),
            'timestamp' => $event->getTimestamp(),
            'transformed_at' => time()
        ];
    })
    ->filter(function ($data) {
        return $data['category'] === 'lifecycle';
    });

$transformed->subscribeEvents(
    function ($data) {
        echo "Transformed event: " . json_encode($data) . "\n";
    }
);

$transformObservable->emitEvent(new RunStarted('transform-run-1', EventType::RUN_STARTED));
$transformObservable->emitEvent(new TextMessageStart('transform-msg-1', EventType::TEXT_MESSAGE_START));

echo "\n=== Examples Complete ===\n";

// Helper function to create text chunk events
function createTextChunk(string $id, ?string $runId, string $content)
{
    return new class($id, EventType::TEXT_MESSAGE_CHUNK, $runId) extends \AGUI\Core\Events\BaseEvent {
        private string $chunkContent;

        public function __construct(string $id, EventType $type, ?string $runId = null, ?int $timestamp = null, ?array $metadata = null, string $content = '')
        {
            $this->chunkContent = $content;
            parent::__construct($id, $type, $runId, $timestamp, $metadata);
        }

        public function getEventData(): array
        {
            return ['content' => $this->chunkContent];
        }
    };
}

// Keep the event loop running for async operations
\React\EventLoop\Loop::run();
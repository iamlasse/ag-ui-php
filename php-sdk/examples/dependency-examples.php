<?php

declare(strict_types=1);

/**
 * AG-UI PHP SDK - Dependency Usage Examples
 *
 * This file demonstrates how to use the PHP equivalents of TypeScript dependencies
 * in the context of AG-UI event-driven communication.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use React\Promise\Promise;
use React\Promise\Deferred;
use Respect\Validation\Validator as v;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPatch;

/**
 * 1. Reactive Programming with React/Promise
 * Similar to rxjs Observable pattern but simpler
 */
class EventEmitter
{
    private array $listeners = [];

    public function on(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $listener;
    }

    public function emit(string $event, mixed $data): Promise
    {
        $deferred = new Deferred();

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                try {
                    $result = $listener($data);
                    $deferred->resolve($result);
                } catch (Throwable $e) {
                    $deferred->reject($e);
                }
            }
        } else {
            $deferred->resolve(null);
        }

        return $deferred->promise();
    }
}

// Example: Event-driven communication
$eventEmitter = new EventEmitter();

// Register event listener
$eventEmitter->on('message', function (array $message) {
    return [
        'type' => 'response',
        'data' => strtoupper($message['text']),
        'timestamp' => time()
    ];
});

// Chain promises like rxjs
$eventEmitter->emit('message', ['text' => 'Hello World'])
    ->then(function ($response) {
        echo "Response: " . json_encode($response) . "\n";
        return $response;
    })
    ->then(function ($response) {
        // Add additional processing
        $response['processed'] = true;
        return $response;
    })
    ->otherwise(function ($error) {
        echo "Error: " . $error->getMessage() . "\n";
    });

/**
 * 2. Schema Validation with Respect/Validation
 * Similar to zod validation patterns
 */
class EventValidator
{
    public static function validateRunAgentInput(array $input): bool
    {
        $validator = v::key('input', v::stringType()->notEmpty())
                      ->key('agentId', v::optional(v::uuid()))
                      ->key('timeout', v::optional(v::intType()->min(1000)))
                      ->key('config', v::optional(v::arrayType()));

        try {
            $validator->assert($input);
            return true;
        } catch (\Respect\Validation\Exceptions\NestedValidationException $e) {
            echo "Validation errors:\n";
            foreach ($e->getMessages() as $field => $message) {
                echo "  - {$field}: {$message}\n";
            }
            return false;
        }
    }

    public static function validateEvent(array $event): bool
    {
        $validator = v::key('type', v::stringType()->in(['RUN_STARTED', 'RUN_FINISHED', 'TEXT_MESSAGE', 'TOOL_CALL']))
                      ->key('data', v::arrayType())
                      ->key('timestamp', v::optional(v::intType()))
                      ->key('id', v::optional(v::uuid()));

        return $validator->validate($event);
    }
}

// Example validation
$validInput = [
    'input' => 'Hello, world!',
    'agentId' => Uuid::uuid4()->toString(),
    'timeout' => 5000
];

echo "Validation result: " . (EventValidator::validateRunAgentInput($validInput) ? 'PASS' : 'FAIL') . "\n";

$invalidInput = [
    'input' => '',  // Invalid: empty string
    'agentId' => 'invalid-uuid'
];

echo "Validation result: " . (EventValidator::validateRunAgentInput($invalidInput) ? 'PASS' : 'FAIL') . "\n";

/**
 * 3. UUID Generation with Ramsey/UUID
 * Similar to uuid library functionality
 */
class EventIdGenerator
{
    public static function generateEventId(): UuidInterface
    {
        return Uuid::uuid4();
    }

    public static function generateRunId(): UuidInterface
    {
        return Uuid::uuid4();
    }

    public static function generateSessionId(string $userId): UuidInterface
    {
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, $userId . time());
    }

    public static function isValidUuid(string $uuid): bool
    {
        return Uuid::isValid($uuid);
    }
}

// Example UUID generation
$eventId = EventIdGenerator::generateEventId();
$runId = EventIdGenerator::generateRunId();
$sessionId = EventIdGenerator::generateSessionId('user123');

echo "Event ID: {$eventId->toString()}\n";
echo "Run ID: {$runId->toString()}\n";
echo "Session ID: {$sessionId->toString()}\n";

/**
 * 4. JSON Patch with Swaggest/Json-Diff
 * Similar to fast-json-patch functionality
 */
class StateManager
{
    private array $state = [];

    public function __construct()
    {
        $this->state = [
            'messages' => [],
            'activeTools' => [],
            'runStatus' => 'idle',
            'metadata' => []
        ];
    }

    public function getSnapshot(): array
    {
        return $this->state;
    }

    public function applyDelta(array $patch): bool
    {
        try {
            $jsonPatch = new JsonPatch();
            $jsonPatch->import(json_encode($patch));
            $jsonPatch->apply($this->state);
            return true;
        } catch (Throwable $e) {
            echo "Failed to apply delta: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function createDelta(array $newState): array
    {
        $diff = new JsonDiff($this->state, $newState);
        return json_decode($diff->getPatch()->toJson(), true);
    }

    public function addMessage(array $message): void
    {
        $newState = $this->state;
        $newState['messages'][] = $message;

        $delta = $this->createDelta($newState);
        $this->applyDelta($delta);
    }

    public function updateRunStatus(string $status): void
    {
        $newState = $this->state;
        $newState['runStatus'] = $status;

        $delta = $this->createDelta($newState);
        $this->applyDelta($delta);
    }
}

// Example state management
$stateManager = new StateManager();

// Initial state
echo "Initial state: " . json_encode($stateManager->getSnapshot(), JSON_PRETTY_PRINT) . "\n";

// Add message using delta
$stateManager->addMessage([
    'id' => Uuid::uuid4()->toString(),
    'type' => 'TEXT_MESSAGE',
    'content' => 'Hello, world!',
    'timestamp' => time()
]);

// Update status using delta
$stateManager->updateRunStatus('running');

// Final state
echo "Updated state: " . json_encode($stateManager->getSnapshot(), JSON_PRETTY_PRINT) . "\n";

/**
 * 5. Combined Example: AG-UI Event Stream
 * Demonstrates all dependencies working together
 */
class AGUIEventStream
{
    private EventEmitter $eventEmitter;
    private EventValidator $validator;
    private EventIdGenerator $idGenerator;
    private StateManager $stateManager;

    public function __construct()
    {
        $this->eventEmitter = new EventEmitter();
        $this->validator = new EventValidator();
        $this->idGenerator = new EventIdGenerator();
        $this->stateManager = new StateManager();
    }

    public function runAgent(array $input): Promise
    {
        if (!$this->validator->validateRunAgentInput($input)) {
            return new Promise(function ($resolve, $reject) {
                $reject(new \InvalidArgumentException('Invalid input'));
            });
        }

        $runId = $this->idGenerator->generateRunId();

        return $this->eventEmitter->emit('run_started', [
            'runId' => $runId->toString(),
            'input' => $input,
            'timestamp' => time()
        ])->then(function () use ($input, $runId) {
            // Simulate agent processing
            return $this->simulateAgentProcessing($input, $runId);
        })->then(function ($results) use ($runId) {
            return $this->eventEmitter->emit('run_finished', [
                'runId' => $runId->toString(),
                'results' => $results,
                'timestamp' => time()
            ]);
        });
    }

    private function simulateAgentProcessing(array $input, UuidInterface $runId): Promise
    {
        $deferred = new Deferred();

        // Simulate async processing
        setTimeout(function () use ($deferred, $input, $runId) {
            $results = [
                'response' => 'Processed: ' . $input['input'],
                'metadata' => [
                    'runId' => $runId->toString(),
                    'processingTime' => rand(100, 1000)
                ]
            ];

            $deferred->resolve($results);
        }, 100);

        return $deferred->promise();
    }
}

// Helper function for async simulation
function setTimeout(callable $callback, int $ms): void
{
    $seconds = $ms / 1000;
    usleep($seconds * 1000000);
    $callback();
}

// Example usage
$stream = new AGUIEventStream();

// Register event handlers
$stream->getEventEmitter()->on('run_started', function ($data) {
    echo "🚀 Run started: {$data['runId']}\n";
});

$stream->getEventEmitter()->on('run_finished', function ($data) {
    echo "✅ Run finished: {$data['runId']}\n";
    echo "Results: " . json_encode($data['results'], JSON_PRETTY_PRINT) . "\n";
});

// Run agent
$input = [
    'input' => 'Hello, AG-UI!',
    'agentId' => Uuid::uuid4()->toString(),
    'timeout' => 5000
];

echo "Starting agent execution...\n";
$stream->runAgent($input)
    ->then(function ($results) {
        echo "Execution completed successfully!\n";
    })
    ->otherwise(function ($error) {
        echo "Execution failed: " . $error->getMessage() . "\n";
    });

echo "End of examples\n";
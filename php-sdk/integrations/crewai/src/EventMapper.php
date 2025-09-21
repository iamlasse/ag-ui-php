<?php

declare(strict_types=1);

namespace AGUI\Integrations\CrewAI;

use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\BaseEvent;
use AGUI\Core\Types\RunAgentInput;
use AGUI\Core\Types\LifecycleEvent;
use AGUI\Core\Types\TextMessageEvent;
use AGUI\Core\Types\ToolCallEvent;
use AGUI\Core\Types\StateEvent;
use AGUI\Core\Observable\Observable;
use AGUI\Core\Observable\Subject;
use Exception;

/**
 * CrewAI Event Mapper
 *
 * Handles translation between CrewAI framework events and AG-UI protocol events.
 * Maps CrewAI's orchestration events to standardized AG-UI events.
 *
 * @package AGUI\Integrations\CrewAI
 */
class EventMapper
{
    private Subject $eventSubject;
    private string $runId;
    private string $threadId;
    private ?string $agentId;
    private array $activeTasks = [];
    private array $completedTasks = [];
    private array $agentStates = [];

    /**
     * EventMapper constructor
     *
     * @param string $runId The current run ID
     * @param string $threadId The thread ID
     * @param string|null $agentId The agent ID
     */
    public function __construct(string $runId, string $threadId, ?string $agentId = null)
    {
        $this->runId = $runId;
        $this->threadId = $threadId;
        $this->agentId = $agentId;
        $this->eventSubject = new Subject();
    }

    /**
     * Get the event observable
     *
     * @return EventObservable
     */
    public function getObservable(): EventObservable
    {
        return new EventObservable($this->eventSubject);
    }

    /**
     * Map CrewAI crew started event
     *
     * @param array $crew Crew configuration
     * @param array $tasks Initial tasks
     * @return void
     */
    public function mapCrewStarted(array $crew, array $tasks): void
    {
        $event = new LifecycleEvent([
            'eventType' => 'CREW_STARTED',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'crew' => $crew,
                'taskCount' => count($tasks),
                'agents' => array_column($crew['agents'] ?? [], 'role')
            ]
        ]);

        $this->emitEvent($event);
        $this->emitStateSnapshot([
            'crewStatus' => 'started',
            'activeTasks' => $this->activeTasks,
            'completedTasks' => $this->completedTasks,
            'agentStates' => $this->agentStates
        ]);
    }

    /**
     * Map CrewAI task started event
     *
     * @param string $taskId Task ID
     * @param string $taskDescription Task description
     * @param string $agentRole Agent role assigned to task
     * @return void
     */
    public function mapTaskStarted(string $taskId, string $taskDescription, string $agentRole): void
    {
        $this->activeTasks[$taskId] = [
            'taskId' => $taskId,
            'description' => $taskDescription,
            'agentRole' => $agentRole,
            'status' => 'in_progress',
            'startTime' => microtime(true)
        ];

        $event = new LifecycleEvent([
            'eventType' => 'TASK_STARTED',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'taskId' => $taskId,
                'taskDescription' => $taskDescription,
                'agentRole' => $agentRole,
                'activeTaskCount' => count($this->activeTasks)
            ]
        ]);

        $this->emitEvent($event);
        $this->emitTaskStateDelta();
    }

    /**
     * Map CrewAI task completed event
     *
     * @param string $taskId Task ID
     * @param mixed $result Task result
     * @param string $agentRole Agent role that completed the task
     * @return void
     */
    public function mapTaskCompleted(string $taskId, $result, string $agentRole): void
    {
        if (isset($this->activeTasks[$taskId])) {
            $task = $this->activeTasks[$taskId];
            $task['status'] = 'completed';
            $task['endTime'] = microtime(true);
            $task['result'] = $result;

            $this->completedTasks[$taskId] = $task;
            unset($this->activeTasks[$taskId]);

            $event = new LifecycleEvent([
                'eventType' => 'TASK_COMPLETED',
                'runId' => $this->runId,
                'threadId' => $this->threadId,
                'agentId' => $this->agentId,
                'timestamp' => microtime(true),
                'data' => [
                    'taskId' => $taskId,
                    'taskDescription' => $task['description'],
                    'agentRole' => $agentRole,
                    'duration' => $task['endTime'] - $task['startTime'],
                    'completedTaskCount' => count($this->completedTasks)
                ]
            ]);

            $this->emitEvent($event);
            $this->emitTaskStateDelta();
        }
    }

    /**
     * Map CrewAI agent thinking event
     *
     * @param string $agentRole Agent role
     * @param string $thought Agent thought process
     * @return void
     */
    public function mapAgentThinking(string $agentRole, string $thought): void
    {
        $event = new TextMessageEvent([
            'eventType' => 'TEXT_MESSAGE_DELTA',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'content' => $thought,
                'role' => 'assistant',
                'agentRole' => $agentRole,
                'messageType' => 'thinking'
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Map CrewAI agent action event
     *
     * @param string $agentRole Agent role
     * @param string $action Action description
     * @param array $actionData Action parameters
     * @return void
     */
    public function mapAgentAction(string $agentRole, string $action, array $actionData = []): void
    {
        $event = new LifecycleEvent([
            'eventType' => 'AGENT_ACTION',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'agentRole' => $agentRole,
                'action' => $action,
                'actionData' => $actionData
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Map CrewAI tool call event
     *
     * @param string $toolName Tool name
     * @param array $parameters Tool parameters
     * @param string $agentRole Agent role making the call
     * @return string Tool call ID
     */
    public function mapToolCall(string $toolName, array $parameters, string $agentRole): string
    {
        $toolCallId = 'tool_' . uniqid();

        $event = new ToolCallEvent([
            'eventType' => 'TOOL_CALL',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'toolCallId' => $toolCallId,
                'toolName' => $toolName,
                'parameters' => $parameters,
                'agentRole' => $agentRole,
                'status' => 'called'
            ]
        ]);

        $this->emitEvent($event);
        return $toolCallId;
    }

    /**
     * Map CrewAI tool result event
     *
     * @param string $toolCallId Tool call ID
     * @param mixed $result Tool result
     * @param bool $success Whether the tool call was successful
     * @return void
     */
    public function mapToolResult(string $toolCallId, $result, bool $success = true): void
    {
        $event = new ToolCallEvent([
            'eventType' => $success ? 'TOOL_CALL_RESULT' : 'TOOL_CALL_ERROR',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'toolCallId' => $toolCallId,
                'result' => $result,
                'status' => $success ? 'completed' : 'error'
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Map CrewAI crew completed event
     *
     * @param array $finalResult Final crew result
     * @param array $taskSummary Summary of all tasks
     * @return void
     */
    public function mapCrewCompleted(array $finalResult, array $taskSummary): void
    {
        $event = new LifecycleEvent([
            'eventType' => 'CREW_COMPLETED',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'finalResult' => $finalResult,
                'taskSummary' => $taskSummary,
                'totalTasks' => count($this->completedTasks),
                'duration' => $this->calculateTotalDuration()
            ]
        ]);

        $this->emitEvent($event);
        $this->emitStateSnapshot([
            'crewStatus' => 'completed',
            'activeTasks' => $this->activeTasks,
            'completedTasks' => $this->completedTasks,
            'agentStates' => $this->agentStates,
            'finalResult' => $finalResult
        ]);
    }

    /**
     * Map CrewAI error event
     *
     * @param string $errorType Type of error
     * @param string $errorMessage Error message
     * @param array $context Error context
     * @return void
     */
    public function mapError(string $errorType, string $errorMessage, array $context = []): void
    {
        $event = new LifecycleEvent([
            'eventType' => 'CREW_ERROR',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'errorType' => $errorType,
                'errorMessage' => $errorMessage,
                'context' => $context
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Map CrewAI process step event
     *
     * @param string $processType Process type (sequential, hierarchical, etc.)
     * @param int $stepNumber Current step number
     * @param int $totalSteps Total steps in process
     * @param string $stepDescription Description of current step
     * @return void
     */
    public function mapProcessStep(string $processType, int $stepNumber, int $totalSteps, string $stepDescription): void
    {
        $event = new LifecycleEvent([
            'eventType' => 'PROCESS_STEP',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'processType' => $processType,
                'stepNumber' => $stepNumber,
                'totalSteps' => $totalSteps,
                'stepDescription' => $stepDescription,
                'progress' => ($stepNumber / $totalSteps) * 100
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Emit a state snapshot event
     *
     * @param array $state State data
     * @return void
     */
    private function emitStateSnapshot(array $state): void
    {
        $event = new StateEvent([
            'eventType' => 'STATE_SNAPSHOT',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => $state
        ]);

        $this->emitEvent($event);
    }

    /**
     * Emit a task state delta event
     *
     * @return void
     */
    private function emitTaskStateDelta(): void
    {
        $event = new StateEvent([
            'eventType' => 'STATE_DELTA',
            'runId' => $this->runId,
            'threadId' => $this->threadId,
            'agentId' => $this->agentId,
            'timestamp' => microtime(true),
            'data' => [
                'activeTasks' => $this->activeTasks,
                'completedTasks' => array_values($this->completedTasks),
                'activeTaskCount' => count($this->activeTasks),
                'completedTaskCount' => count($this->completedTasks)
            ]
        ]);

        $this->emitEvent($event);
    }

    /**
     * Calculate total duration of all completed tasks
     *
     * @return float Total duration in seconds
     */
    private function calculateTotalDuration(): float
    {
        $totalDuration = 0.0;
        foreach ($this->completedTasks as $task) {
            if (isset($task['startTime']) && isset($task['endTime'])) {
                $totalDuration += $task['endTime'] - $task['startTime'];
            }
        }
        return $totalDuration;
    }

    /**
     * Emit an event to the subject
     *
     * @param BaseEvent $event The event to emit
     * @return void
     */
    private function emitEvent(BaseEvent $event): void
    {
        $this->eventSubject->next($event);
    }

    /**
     * Complete the event stream
     *
     * @return void
     */
    public function complete(): void
    {
        $this->eventSubject->complete();
    }

    /**
     * Handle errors in the event stream
     *
     * @param Exception $error The error to handle
     * @return void
     */
    public function error(Exception $error): void
    {
        $this->eventSubject->error($error);
    }

    /**
     * Get current task statistics
     *
     * @return array
     */
    public function getTaskStats(): array
    {
        return [
            'activeTaskCount' => count($this->activeTasks),
            'completedTaskCount' => count($this->completedTasks),
            'totalTaskCount' => count($this->activeTasks) + count($this->completedTasks),
            'completionRate' => $this->calculateCompletionRate()
        ];
    }

    /**
     * Calculate task completion rate
     *
     * @return float
     */
    private function calculateCompletionRate(): float
    {
        $total = count($this->activeTasks) + count($this->completedTasks);
        if ($total === 0) {
            return 0.0;
        }
        return (count($this->completedTasks) / $total) * 100;
    }
}
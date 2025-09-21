<?php

declare(strict_types=1);

namespace AGUI\Integrations\CrewAI;

use AGUI\Core\Agent\AbstractAgent;
use AGUI\Core\Observable\EventObservable;
use AGUI\Core\Types\RunAgentInput;
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
use AGUI\Core\Events\MessagesSnapshot;
use AGUI\Core\Events\StepStarted;
use AGUI\Core\Events\StepFinished;
use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
use CrewAI\CrewAI;
use CrewAI\Agent as CrewAIAgentCore;
use CrewAI\Task as CrewAITask;
use CrewAI\Crew as CrewAICrew;
use CrewAI\Process as CrewAIProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * CrewAI Agent implementation for AG-UI protocol
 *
 * This class implements the AbstractAgent interface to provide CrewAI
 * orchestration capabilities with AG-UI protocol event translation.
 *
 * @package AGUI\Integrations\CrewAI
 */
class CrewAIAgent extends AbstractAgent
{
    private ?CrewAI $crewAI = null;
    private ?CrewAICrew $crew = null;
    private array $crewAgents = [];
    private array $crewTasks = [];
    private ?CrewAIProcess $process = null;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $baseUrl;
    private ?EventObservable $eventObservable = null;
    private array $activeTaskStates = [];
    private array $messageTracking = [];
    private array $toolCallTracking = [];
    private ?string $activeStep = null;
    private array $crewExecutionState = [];
    private CrewOrchestrator $orchestrator;

    /**
     * CrewAIAgent configuration
     *
     * @param array{
     *     agentId?: string,
     *     description?: string,
     *     threadId?: string,
     *     initialMessages?: array,
     *     initialState?: mixed,
     *     debug?: bool,
     *     apiKey?: string,
     *     baseUrl?: string,
     *     crewConfig?: array{
     *         agents?: array,
     *         tasks?: array,
     *         process?: string,
     *         verbose?: bool
     *     },
     *     logger?: LoggerInterface
     * } $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->apiKey = $config['apiKey'] ?? $_ENV['CREWAI_API_KEY'] ?? '';
        $this->baseUrl = $config['baseUrl'] ?? 'https://api.crewai.com';
        $this->logger = $config['logger'] ?? new NullLogger();

        if (empty($this->apiKey)) {
            throw new RuntimeException('CrewAI API key is required');
        }

        // Initialize CrewAI configuration
        $this->initializeCrewAI($config['crewConfig'] ?? []);

        // Initialize orchestrator
        $this->orchestrator = new CrewOrchestrator(
            $this->generateUuid(),
            $this->threadId,
            $this->agentId,
            $config['crewConfig'] ?? []
        );

        // Create event observable for streaming
        $this->eventObservable = $this->orchestrator->getObservable();
    }

    /**
     * Initialize CrewAI components
     *
     * @param array $crewConfig
     * @return void
     */
    private function initializeCrewAI(array $crewConfig): void
    {
        $this->crewAI = new CrewAI([
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
        ]);

        // Initialize crew agents
        if (!empty($crewConfig['agents'])) {
            $this->initializeCrewAgents($crewConfig['agents']);
        }

        // Initialize crew tasks
        if (!empty($crewConfig['tasks'])) {
            $this->initializeCrewTasks($crewConfig['tasks']);
        }

        // Initialize crew process
        $processType = $crewConfig['process'] ?? 'sequential';
        $this->process = CrewAIProcess::fromType($processType);

        // Create the crew
        $this->createCrew();
    }

    /**
     * Initialize crew agents
     *
     * @param array $agentsConfig
     * @return void
     */
    private function initializeCrewAgents(array $agentsConfig): void
    {
        $this->crewAgents = [];

        foreach ($agentsConfig as $agentConfig) {
            $agent = new CrewAIAgentCore([
                'role' => $agentConfig['role'] ?? 'Assistant',
                'goal' => $agentConfig['goal'] ?? 'Help accomplish tasks',
                'backstory' => $agentConfig['backstory'] ?? 'AI assistant',
                'allow_delegation' => $agentConfig['allow_delegation'] ?? true,
                'verbose' => $agentConfig['verbose'] ?? $this->debug,
                'tools' => $agentConfig['tools'] ?? [],
            ]);

            $this->crewAgents[] = $agent;
        }
    }

    /**
     * Initialize crew tasks
     *
     * @param array $tasksConfig
     * @return void
     */
    private function initializeCrewTasks(array $tasksConfig): void
    {
        $this->crewTasks = [];

        foreach ($tasksConfig as $taskConfig) {
            $task = new CrewAITask([
                'description' => $taskConfig['description'] ?? 'Task',
                'expected_output' => $taskConfig['expected_output'] ?? 'Output',
                'agent' => $this->getCrewAgentByRole($taskConfig['agent_role'] ?? null),
                'tools' => $taskConfig['tools'] ?? [],
                'async_execution' => $taskConfig['async_execution'] ?? false,
                'context' => $taskConfig['context'] ?? [],
                'output_file' => $taskConfig['output_file'] ?? null,
                'output_json' => $taskConfig['output_json'] ?? null,
            ]);

            $this->crewTasks[] = $task;
        }
    }

    /**
     * Get crew agent by role
     *
     * @param string|null $role
     * @return CrewAIAgentCore|null
     */
    private function getCrewAgentByRole(?string $role): ?CrewAIAgentCore
    {
        if ($role === null) {
            return null;
        }

        foreach ($this->crewAgents as $agent) {
            if ($agent->getRole() === $role) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Create the crew
     *
     * @return void
     */
    private function createCrew(): void
    {
        if (empty($this->crewAgents) || empty($this->crewTasks)) {
            throw new RuntimeException('Both agents and tasks are required to create a crew');
        }

        $this->crew = new CrewAICrew([
            'agents' => $this->crewAgents,
            'tasks' => $this->crewTasks,
            'process' => $this->process,
            'verbose' => $this->debug,
        ]);
    }

    /**
     * Run the agent with the given input
     *
     * @param RunAgentInput $input The input for running the agent
     * @return EventObservable Observable stream of events
     */
    public function run(RunAgentInput $input): EventObservable
    {
        $runId = $input->getRunId();
        $threadId = $input->getThreadId();

        $this->logger->info("Starting CrewAI run", [
            'run_id' => $runId,
            'thread_id' => $threadId,
        ]);

        // Reset tracking state
        $this->resetTrackingState();

        // Update orchestrator with correct run ID
        $this->orchestrator = new CrewOrchestrator(
            $runId,
            $threadId,
            $this->agentId,
            $this->crewExecutionState
        );

        // Initialize crew with agents and tasks
        if (!empty($this->crewAgents) && !empty($this->crewTasks)) {
            $this->orchestrator->initializeCrew($this->crewAgents, $this->crewTasks);
        }

        // Start the crew execution in background
        $this->executeCrewAsync($input, $this->orchestrator->getObservable());

        return $this->orchestrator->getObservable();
    }

    /**
     * Execute crew asynchronously and emit events
     *
     * @param RunAgentInput $input
     * @param EventObservable $observable
     * @return void
     */
    private function executeCrewAsync(RunAgentInput $input, EventObservable $observable): void
    {
        try {
            // Execute crew using orchestrator
            $result = $this->orchestrator->executeCrew();

            // Process the result and update state
            $this->processCrewResult($result, $input);

        } catch (\Throwable $e) {
            $this->logger->error("CrewAI execution failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $observable->emitError($e);
            return;
        }

        // Emit run finished event
        $runFinished = new RunFinished(
            $this->generateUuid(),
            EventType::RUN_FINISHED,
            $input->getThreadId()
        );
        $observable->emitEvent($runFinished);
        $observable->complete();
    }

    /**
     * Prepare crew input from AG-UI input
     *
     * @param RunAgentInput $input
     * @return array
     */
    private function prepareCrewInput(RunAgentInput $input): array
    {
        $crewInput = [
            'topic' => $this->extractTopicFromMessages($input->getMessages()),
            'messages' => $this->convertMessagesToCrewFormat($input->getMessages()),
            'tools' => $input->getTools(),
            'context' => $input->getContext(),
            'state' => $input->getState(),
        ];

        return $crewInput;
    }

    /**
     * Extract topic from messages
     *
     * @param array $messages
     * @return string
     */
    private function extractTopicFromMessages(array $messages): string
    {
        if (empty($messages)) {
            return 'General task';
        }

        // Get the last user message as the topic
        $userMessages = array_filter($messages, function ($message) {
            return isset($message['role']) && $message['role'] === 'user';
        });

        if (empty($userMessages)) {
            return 'General task';
        }

        $lastUserMessage = end($userMessages);
        return substr($lastUserMessage['content'] ?? '', 0, 100);
    }

    /**
     * Convert AG-UI messages to CrewAI format
     *
     * @param array $messages
     * @return array
     */
    private function convertMessagesToCrewFormat(array $messages): array
    {
        $crewMessages = [];

        foreach ($messages as $message) {
            $crewMessage = [
                'role' => $message['role'] ?? 'user',
                'content' => $message['content'] ?? '',
            ];

            if (isset($message['tool_calls'])) {
                $crewMessage['tool_calls'] = $message['tool_calls'];
            }

            $crewMessages[] = $crewMessage;
        }

        return $crewMessages;
    }

    /**
     * Process crew execution result and emit events
     *
     * @param array $result
     * @param RunAgentInput $input
     * @param EventObservable $observable
     * @return void
     */
    private function processCrewResult(array $result, RunAgentInput $input): void
    {
        $this->logger->info("Processing crew result", ['result' => $result]);

        // Update agent state with crew result
        $this->setState([
            'crewResult' => $result,
            'executionStats' => $this->orchestrator->getExecutionStats(),
            'lastRun' => [
                'runId' => $input->getRunId(),
                'timestamp' => microtime(true)
            ]
        ]);

        // Process task results
        if (isset($result['tasks_output'])) {
            $this->processTaskOutputs($result['tasks_output'], $input);
        }

        // Process agent interactions
        if (isset($result['agent_interactions'])) {
            $this->processAgentInteractions($result['agent_interactions'], $input);
        }

        // Emit final messages
        if (isset($result['final_message'])) {
            $this->emitTextMessage($result['final_message'], $input->getThreadId(), $observable);
        }
    }

    /**
     * Process task outputs
     *
     * @param array $tasksOutput
     * @param RunAgentInput $input
     * @param EventObservable $observable
     * @return void
     */
    private function processTaskOutputs(array $tasksOutput, RunAgentInput $input, EventObservable $observable): void
    {
        foreach ($tasksOutput as $taskOutput) {
            $taskId = $taskOutput['task_id'] ?? $this->generateUuid();

            // Emit step started for this task
            $stepStarted = new StepStarted(
                $taskId,
                EventType::STEP_STARTED,
                $input->getThreadId()
            );
            $stepStarted->setEventData(['step_name' => $taskOutput['task'] ?? 'Unknown Task']);
            $observable->emitEvent($stepStarted);

            // Process task content as text message
            if (isset($taskOutput['output'])) {
                $this->emitTextMessage(
                    $taskOutput['output'],
                    $input->getThreadId(),
                    $observable,
                    $taskOutput['task'] ?? 'Task Output'
                );
            }

            // Emit step finished
            $stepFinished = new StepFinished(
                $taskId,
                EventType::STEP_FINISHED,
                $input->getThreadId()
            );
            $stepFinished->setEventData(['step_name' => $taskOutput['task'] ?? 'Unknown Task']);
            $observable->emitEvent($stepFinished);
        }
    }

    /**
     * Process agent interactions
     *
     * @param array $interactions
     * @param RunAgentInput $input
     * @param EventObservable $observable
     * @return void
     */
    private function processAgentInteractions(array $interactions, RunAgentInput $input, EventObservable $observable): void
    {
        foreach ($interactions as $interaction) {
            // Process agent messages
            if (isset($interaction['message'])) {
                $this->emitTextMessage(
                    $interaction['message'],
                    $input->getThreadId(),
                    $observable,
                    $interaction['agent'] ?? 'Agent'
                );
            }

            // Process tool calls
            if (isset($interaction['tool_calls'])) {
                $this->processToolCalls($interaction['tool_calls'], $input, $observable);
            }
        }
    }

    /**
     * Process tool calls
     *
     * @param array $toolCalls
     * @param RunAgentInput $input
     * @param EventObservable $observable
     * @return void
     */
    private function processToolCalls(array $toolCalls, RunAgentInput $input, EventObservable $observable): void
    {
        foreach ($toolCalls as $toolCall) {
            $toolCallId = $toolCall['id'] ?? $this->generateUuid();

            // Emit tool call start
            $toolCallStart = new ToolCallStart(
                $toolCallId,
                EventType::TOOL_CALL_START,
                $input->getThreadId()
            );
            $toolCallStart->setEventData([
                'tool' => $toolCall['function'] ?? 'unknown',
                'input' => $toolCall['arguments'] ?? []
            ]);
            $observable->emitEvent($toolCallStart);

            // Emit tool call chunks (if any)
            if (isset($toolCall['output'])) {
                $toolCallChunk = new ToolCallChunk(
                    $this->generateUuid(),
                    EventType::TOOL_CALL_CHUNK,
                    $input->getThreadId()
                );
                $toolCallChunk->setEventData([
                    'content' => $toolCall['output']
                ]);
                $observable->emitEvent($toolCallChunk);
            }

            // Emit tool call end
            $toolCallEnd = new ToolCallEnd(
                $toolCallId,
                EventType::TOOL_CALL_END,
                $input->getThreadId()
            );
            $toolCallEnd->setEventData([
                'status' => 'completed'
            ]);
            $observable->emitEvent($toolCallEnd);
        }
    }

    /**
     * Emit text message events
     *
     * @param string $content
     * @param string $threadId
     * @param EventObservable $observable
     * @param string $source
     * @return void
     */
    private function emitTextMessage(string $content, string $threadId, EventObservable $observable, string $source = 'CrewAI'): void
    {
        $messageId = $this->generateUuid();

        // Emit start
        $messageStart = new TextMessageStart(
            $messageId,
            EventType::TEXT_MESSAGE_START,
            $threadId
        );
        $messageStart->setEventData([
            'message_id' => $messageId,
            'role' => 'assistant',
            'source' => $source
        ]);
        $observable->emitEvent($messageStart);

        // Emit content (chunked if long)
        $chunks = str_split($content, 1000); // Split into 1000 character chunks
        foreach ($chunks as $chunk) {
            $messageChunk = new TextMessageChunk(
                $this->generateUuid(),
                EventType::TEXT_MESSAGE_CHUNK,
                $threadId
            );
            $messageChunk->setEventData([
                'message_id' => $messageId,
                'content' => $chunk
            ]);
            $observable->emitEvent($messageChunk);
        }

        // Emit end
        $messageEnd = new TextMessageEnd(
            $messageId,
            EventType::TEXT_MESSAGE_END,
            $threadId
        );
        $messageEnd->setEventData([
            'message_id' => $messageId,
            'role' => 'assistant',
            'source' => $source
        ]);
        $observable->emitEvent($messageEnd);
    }

    /**
     * Reset tracking state for new run
     *
     * @return void
     */
    private function resetTrackingState(): void
    {
        $this->activeTaskStates = [];
        $this->messageTracking = [];
        $this->toolCallTracking = [];
        $this->activeStep = null;
        $this->crewExecutionState = [];
    }

    /**
     * Clone the agent
     *
     * @return static
     */
    public function clone(): static
    {
        $cloned = new static([
            'agentId' => $this->generateUuid(),
            'description' => $this->description,
            'threadId' => $this->generateUuid(),
            'initialMessages' => $this->messages,
            'initialState' => $this->state,
            'debug' => $this->debug,
            'apiKey' => $this->apiKey,
            'baseUrl' => $this->baseUrl,
            'crewConfig' => [
                'agents' => array_map(function($agent) {
                    return [
                        'role' => $agent->getRole(),
                        'goal' => $agent->getGoal(),
                        'backstory' => $agent->getBackstory(),
                        'allow_delegation' => $agent->allowsDelegation(),
                        'verbose' => $agent->isVerbose(),
                        'tools' => $agent->getTools(),
                    ];
                }, $this->crewAgents),
                'tasks' => array_map(function($task) {
                    return [
                        'description' => $task->getDescription(),
                        'expected_output' => $task->getExpectedOutput(),
                        'agent_role' => $task->getAgent()?->getRole(),
                        'tools' => $task->getTools(),
                        'async_execution' => $task->isAsyncExecution(),
                        'context' => $task->getContext(),
                    ];
                }, $this->crewTasks),
                'process' => $this->process?->getType() ?? 'sequential',
            ],
            'logger' => $this->logger,
        ]);

        return $cloned;
    }

    /**
     * Get the CrewAI instance
     *
     * @return CrewAI|null
     */
    public function getCrewAI(): ?CrewAI
    {
        return $this->crewAI;
    }

    /**
     * Get the Crew instance
     *
     * @return CrewAICrew|null
     */
    public function getCrew(): ?CrewAICrew
    {
        return $this->crew;
    }

    /**
     * Get crew agents
     *
     * @return array
     */
    public function getCrewAgents(): array
    {
        return $this->crewAgents;
    }

    /**
     * Get crew tasks
     *
     * @return array
     */
    public function getCrewTasks(): array
    {
        return $this->crewTasks;
    }

    /**
     * Add a new crew agent
     *
     * @param array $agentConfig
     * @return void
     */
    public function addCrewAgent(array $agentConfig): void
    {
        $agent = new CrewAIAgentCore([
            'role' => $agentConfig['role'] ?? 'Assistant',
            'goal' => $agentConfig['goal'] ?? 'Help accomplish tasks',
            'backstory' => $agentConfig['backstory'] ?? 'AI assistant',
            'allow_delegation' => $agentConfig['allow_delegation'] ?? true,
            'verbose' => $agentConfig['verbose'] ?? $this->debug,
            'tools' => $agentConfig['tools'] ?? [],
        ]);

        $this->crewAgents[] = $agent;

        // Recreate crew with updated agents
        $this->createCrew();
    }

    /**
     * Add a new crew task
     *
     * @param array $taskConfig
     * @return void
     */
    public function addCrewTask(array $taskConfig): void
    {
        $task = new CrewAITask([
            'description' => $taskConfig['description'] ?? 'Task',
            'expected_output' => $taskConfig['expected_output'] ?? 'Output',
            'agent' => $this->getCrewAgentByRole($taskConfig['agent_role'] ?? null),
            'tools' => $taskConfig['tools'] ?? [],
            'async_execution' => $taskConfig['async_execution'] ?? false,
            'context' => $taskConfig['context'] ?? [],
            'output_file' => $taskConfig['output_file'] ?? null,
            'output_json' => $taskConfig['output_json'] ?? null,
        ]);

        $this->crewTasks[] = $task;

        // Recreate crew with updated tasks
        $this->createCrew();
    }

    /**
     * Get crew execution state
     *
     * @return array
     */
    public function getCrewExecutionState(): array
    {
        return $this->crewExecutionState;
    }
}
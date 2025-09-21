<?php

declare(strict_types=1);

namespace AGUI\Integrations\CrewAI;

use Exception;
use AGUI\Core\Types\RunAgentInput;
use AGUI\Core\Agent\AbstractAgent;
use AGUI\Core\Observable\EventObservable;

/**
 * CrewAI Orchestrator
 *
 * Manages CrewAI crew coordination, task distribution, and multi-agent workflows.
 * Handles crew lifecycle, task assignment, and agent coordination.
 *
 * @package AGUI\Integrations\CrewAI
 */
class CrewOrchestrator
{
    private EventMapper $eventMapper;
    private array $crewConfig;
    private array $tasks = [];
    private array $agents = [];
    private bool $isRunning = false;
    private ?string $currentProcess = null;
    private array $processSteps = [];
    private int $currentStep = 0;

    /**
     * CrewOrchestrator constructor
     *
     * @param string $runId The run ID
     * @param string $threadId The thread ID
     * @param string|null $agentId The agent ID
     * @param array $crewConfig Crew configuration
     */
    public function __construct(string $runId, string $threadId, ?string $agentId = null, array $crewConfig = [])
    {
        $this->eventMapper = new EventMapper($runId, $threadId, $agentId);
        $this->crewConfig = array_merge([
            'process' => 'sequential',
            'verbose' => true,
            'maxRpm' => null,
            'shareLLM' => true,
            'cacheLLM' => true
        ], $crewConfig);
    }

    /**
     * Get the event observable
     *
     * @return EventObservable
     */
    public function getObservable(): EventObservable
    {
        return $this->eventMapper->getObservable();
    }

    /**
     * Initialize the crew with agents and tasks
     *
     * @param array $agents Array of agent configurations
     * @param array $tasks Array of task configurations
     * @return void
     * @throws Exception
     */
    public function initializeCrew(array $agents, array $tasks): void
    {
        if ($this->isRunning) {
            throw new Exception('Crew is already running');
        }

        $this->agents = $this->validateAndPrepareAgents($agents);
        $this->tasks = $this->validateAndPrepareTasks($tasks);
        $this->crewConfig['agents'] = $this->agents;

        $this->eventMapper->mapCrewStarted($this->crewConfig, $this->tasks);
    }

    /**
     * Execute the crew workflow
     *
     * @return array Execution result
     * @throws Exception
     */
    public function executeCrew(): array
    {
        if (empty($this->agents) || empty($this->tasks)) {
            throw new Exception('Crew must be initialized with agents and tasks');
        }

        $this->isRunning = true;
        $this->currentProcess = $this->crewConfig['process'];

        try {
            switch ($this->currentProcess) {
                case 'sequential':
                    $result = $this->executeSequentialProcess();
                    break;
                case 'hierarchical':
                    $result = $this->executeHierarchicalProcess();
                    break;
                case 'consensus':
                    $result = $this->executeConsensusProcess();
                    break;
                default:
                    throw new Exception("Unsupported process type: {$this->currentProcess}");
            }

            $taskSummary = $this->generateTaskSummary();
            $this->eventMapper->mapCrewCompleted($result, $taskSummary);

            return $result;
        } catch (Exception $e) {
            $this->eventMapper->mapError('execution_error', $e->getMessage(), [
                'process' => $this->currentProcess,
                'currentStep' => $this->currentStep
            ]);
            throw $e;
        } finally {
            $this->isRunning = false;
            $this->eventMapper->complete();
        }
    }

    /**
     * Execute sequential process (tasks run one after another)
     *
     * @return array
     */
    private function executeSequentialProcess(): array
    {
        $this->processSteps = $this->tasks;
        $results = [];

        foreach ($this->tasks as $index => $task) {
            $this->currentStep = $index + 1;

            $this->eventMapper->mapProcessStep(
                'sequential',
                $this->currentStep,
                count($this->tasks),
                "Executing task: {$task['description']}"
            );

            $result = $this->executeTask($task, $this->findBestAgentForTask($task));
            $results[] = $result;
        }

        return $this->consolidateResults($results);
    }

    /**
     * Execute hierarchical process (manager agent coordinates task execution)
     *
     * @return array
     */
    private function executeHierarchicalProcess(): array
    {
        $managerAgent = $this->findManagerAgent();
        if (!$managerAgent) {
            throw new Exception('No manager agent found for hierarchical process');
        }

        $this->processSteps = array_merge(
            [['type' => 'planning', 'description' => 'Manager planning phase']],
            $this->tasks,
            [['type' => 'synthesis', 'description' => 'Manager synthesis phase']]
        );

        // Manager planning phase
        $this->currentStep = 1;
        $this->eventMapper->mapProcessStep(
            'hierarchical',
            $this->currentStep,
            count($this->processSteps),
            "Manager planning task execution"
        );

        $plan = $this->executeManagerPlanning($managerAgent);

        // Execute tasks
        $taskResults = [];
        foreach ($this->tasks as $index => $task) {
            $this->currentStep = $index + 2;

            $this->eventMapper->mapProcessStep(
                'hierarchical',
                $this->currentStep,
                count($this->processSteps),
                "Executing task: {$task['description']}"
            );

            $result = $this->executeTask($task, $this->findBestAgentForTask($task));
            $taskResults[] = $result;
        }

        // Manager synthesis phase
        $this->currentStep = count($this->processSteps);
        $this->eventMapper->mapProcessStep(
            'hierarchical',
            $this->currentStep,
            count($this->processSteps),
            "Manager synthesizing results"
        );

        $finalResult = $this->executeManagerSynthesis($managerAgent, $taskResults, $plan);

        return $finalResult;
    }

    /**
     * Execute consensus process (multiple agents work on tasks and reach consensus)
     *
     * @return array
     */
    private function executeConsensusProcess(): array
    {
        $this->processSteps = $this->tasks;
        $consensusResults = [];

        foreach ($this->tasks as $index => $task) {
            $this->currentStep = $index + 1;

            $this->eventMapper->mapProcessStep(
                'consensus',
                $this->currentStep,
                count($this->tasks),
                "Building consensus for task: {$task['description']}"
            );

            $taskResults = [];
            $eligibleAgents = $this->findEligibleAgentsForTask($task);

            // Have multiple agents work on the same task
            foreach ($eligibleAgents as $agent) {
                $result = $this->executeTask($task, $agent);
                $taskResults[] = [
                    'agent' => $agent,
                    'result' => $result
                ];
            }

            // Build consensus
            $consensus = $this->buildConsensus($task, $taskResults);
            $consensusResults[] = $consensus;
        }

        return $this->consolidateResults($consensusResults);
    }

    /**
     * Execute a single task
     *
     * @param array $task Task configuration
     * @param array $agent Agent configuration
     * @return array Task result
     */
    private function executeTask(array $task, array $agent): array
    {
        $taskId = $task['id'] ?? uniqid('task_');

        $this->eventMapper->mapTaskStarted(
            $taskId,
            $task['description'],
            $agent['role']
        );

        // Simulate agent thinking process
        $this->eventMapper->mapAgentThinking(
            $agent['role'],
            "Analyzing task: {$task['description']}"
        );

        try {
            // Execute the task with agent tools
            $result = $this->executeTaskWithAgent($task, $agent);

            $this->eventMapper->mapTaskCompleted(
                $taskId,
                $result,
                $agent['role']
            );

            return $result;
        } catch (Exception $e) {
            $this->eventMapper->mapError(
                'task_execution_error',
                $e->getMessage(),
                [
                    'taskId' => $taskId,
                    'agentRole' => $agent['role'],
                    'taskDescription' => $task['description']
                ]
            );
            throw $e;
        }
    }

    /**
     * Execute task using agent capabilities
     *
     * @param array $task Task configuration
     * @param array $agent Agent configuration
     * @return array
     */
    private function executeTaskWithAgent(array $task, array $agent): array
    {
        $result = [
            'taskId' => $task['id'] ?? uniqid('task_'),
            'agentRole' => $agent['role'],
            'description' => $task['description'],
            'output' => null,
            'toolsUsed' => [],
            'duration' => 0,
            'success' => true
        ];

        $startTime = microtime(true);

        // Simulate agent action
        $this->eventMapper->mapAgentAction(
            $agent['role'],
            'execute_task',
            ['taskId' => $result['taskId'], 'description' => $task['description']]
        );

        // Simulate tool usage if needed
        if (!empty($task['expectedOutput'])) {
            $toolResult = $this->simulateToolUsage($agent, $task);
            $result['toolsUsed'][] = $toolResult;
            $result['output'] = $toolResult['output'];
        } else {
            // Simulate direct task completion
            $result['output'] = $this->generateTaskOutput($task, $agent);
        }

        $result['duration'] = microtime(true) - $startTime;

        return $result;
    }

    /**
     * Simulate tool usage by agent
     *
     * @param array $agent Agent configuration
     * @param array $task Task configuration
     * @return array Tool result
     */
    private function simulateToolUsage(array $agent, array $task): array
    {
        $toolName = $task['tool'] ?? 'default_tool';
        $parameters = $task['parameters'] ?? [];

        $toolCallId = $this->eventMapper->mapToolCall(
            $toolName,
            $parameters,
            $agent['role']
        );

        // Simulate tool execution
        sleep(0.1); // Simulate processing time
        $toolOutput = $this->generateToolOutput($toolName, $parameters, $task);

        $this->eventMapper->mapToolResult(
            $toolCallId,
            $toolOutput,
            true
        );

        return [
            'toolCallId' => $toolCallId,
            'toolName' => $toolName,
            'parameters' => $parameters,
            'output' => $toolOutput,
            'success' => true
        ];
    }

    /**
     * Generate task output based on agent and task
     *
     * @param array $task Task configuration
     * @param array $agent Agent configuration
     * @return mixed
     */
    private function generateTaskOutput(array $task, array $agent)
    {
        // This is a simplified simulation
        // In a real implementation, this would call the actual CrewAI agents
        return [
            'content' => "Task '{$task['description']}' completed by {$agent['role']}",
            'confidence' => 0.9,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Generate tool output
     *
     * @param string $toolName Tool name
     * @param array $parameters Tool parameters
     * @param array $task Task context
     * @return mixed
     */
    private function generateToolOutput(string $toolName, array $parameters, array $task)
    {
        // This is a simplified simulation
        // In a real implementation, this would call actual tools
        return [
            'tool' => $toolName,
            'result' => "Tool {$toolName} executed successfully",
            'parameters' => $parameters,
            'taskContext' => $task['description']
        ];
    }

    /**
     * Find the best agent for a task
     *
     * @param array $task Task configuration
     * @return array Best agent
     */
    private function findBestAgentForTask(array $task): array
    {
        // Simple matching based on agent role and task requirements
        $requiredSkills = $task['skills'] ?? [];

        foreach ($this->agents as $agent) {
            $agentSkills = $agent['skills'] ?? [];
            if (empty($requiredSkills) || count(array_intersect($agentSkills, $requiredSkills)) > 0) {
                return $agent;
            }
        }

        // Return first agent if no specific match
        return $this->agents[0];
    }

    /**
     * Find manager agent for hierarchical process
     *
     * @return array|null Manager agent
     */
    private function findManagerAgent(): ?array
    {
        foreach ($this->agents as $agent) {
            if (isset($agent['role']) &&
                (stripos($agent['role'], 'manager') !== false ||
                 stripos($agent['role'], 'coordinator') !== false)) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * Find all eligible agents for a task
     *
     * @param array $task Task configuration
     * @return array Eligible agents
     */
    private function findEligibleAgentsForTask(array $task): array
    {
        $eligibleAgents = [];
        $requiredSkills = $task['skills'] ?? [];

        foreach ($this->agents as $agent) {
            $agentSkills = $agent['skills'] ?? [];
            if (empty($requiredSkills) || count(array_intersect($agentSkills, $requiredSkills)) > 0) {
                $eligibleAgents[] = $agent;
            }
        }

        return $eligibleAgents;
    }

    /**
     * Execute manager planning phase
     *
     * @param array $managerAgent Manager agent
     * @return array Plan
     */
    private function executeManagerPlanning(array $managerAgent): array
    {
        $this->eventMapper->mapAgentThinking(
            $managerAgent['role'],
            "Planning task execution strategy for " . count($this->tasks) . " tasks"
        );

        return [
            'strategy' => 'sequential_execution',
            'resourceAllocation' => [],
            'timeline' => [],
            'riskAssessment' => []
        ];
    }

    /**
     * Execute manager synthesis phase
     *
     * @param array $managerAgent Manager agent
     * @param array $taskResults Task results
     * @param array $plan Original plan
     * @return array Final result
     */
    private function executeManagerSynthesis(array $managerAgent, array $taskResults, array $plan): array
    {
        $this->eventMapper->mapAgentThinking(
            $managerAgent['role'],
            "Synthesizing results from " . count($taskResults) . " completed tasks"
        );

        return [
            'summary' => 'Crew completed all tasks successfully',
            'taskResults' => $taskResults,
            'insights' => [],
            'recommendations' => [],
            'planExecution' => $plan
        ];
    }

    /**
     * Build consensus from multiple agent results
     *
     * @param array $task Task configuration
     * @param array $agentResults Results from different agents
     * @return array Consensus result
     */
    private function buildConsensus(array $task, array $agentResults): array
    {
        $this->eventMapper->mapAgentThinking(
            'consensus_builder',
            "Building consensus from " . count($agentResults) . " agent results"
        );

        return [
            'taskId' => $task['id'] ?? uniqid('task_'),
            'description' => $task['description'],
            'consensusType' => 'majority_vote',
            'agentResults' => $agentResults,
            'finalDecision' => $agentResults[0]['result'] // Simplified consensus
        ];
    }

    /**
     * Consolidate multiple results into final result
     *
     * @param array $results Individual results
     * @return array Consolidated result
     */
    private function consolidateResults(array $results): array
    {
        return [
            'type' => 'consolidated_result',
            'individualResults' => $results,
            'summary' => 'Successfully completed ' . count($results) . ' tasks',
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Generate task summary
     *
     * @return array
     */
    private function generateTaskSummary(): array
    {
        return [
            'totalTasks' => count($this->tasks),
            'completedTasks' => count($this->tasks),
            'processType' => $this->currentProcess,
            'duration' => 0, // Would be calculated in real implementation
            'agentParticipation' => array_column($this->agents, 'role'),
            'toolsUsed' => [] // Would be populated in real implementation
        ];
    }

    /**
     * Validate and prepare agents
     *
     * @param array $agents Raw agent configurations
     * @return array Validated agents
     * @throws Exception
     */
    private function validateAndPrepareAgents(array $agents): array
    {
        if (empty($agents)) {
            throw new Exception('At least one agent is required');
        }

        $validatedAgents = [];
        foreach ($agents as $index => $agent) {
            if (!isset($agent['role'])) {
                throw new Exception("Agent at index {$index} must have a role");
            }

            $validatedAgents[] = array_merge([
                'id' => uniqid('agent_'),
                'skills' => [],
                'backstory' => '',
                'goal' => '',
                'verbose' => false,
                'allowDelegation' => true,
                'maxRpm' => null,
                'cacheLLM' => true,
                'shareLLM' => true
            ], $agent);
        }

        return $validatedAgents;
    }

    /**
     * Validate and prepare tasks
     *
     * @param array $tasks Raw task configurations
     * @return array Validated tasks
     * @throws Exception
     */
    private function validateAndPrepareTasks(array $tasks): array
    {
        if (empty($tasks)) {
            throw new Exception('At least one task is required');
        }

        $validatedTasks = [];
        foreach ($tasks as $index => $task) {
            if (!isset($task['description'])) {
                throw new Exception("Task at index {$index} must have a description");
            }

            $validatedTasks[] = array_merge([
                'id' => uniqid('task_'),
                'expectedOutput' => '',
                'asyncExecution' => false,
                'context' => [],
                'tools' => [],
                'parameters' => [],
                'skills' => []
            ], $task);
        }

        return $validatedTasks;
    }

    /**
     * Get current execution statistics
     *
     * @return array
     */
    public function getExecutionStats(): array
    {
        return [
            'isRunning' => $this->isRunning,
            'currentProcess' => $this->currentProcess,
            'currentStep' => $this->currentStep,
            'totalSteps' => count($this->processSteps),
            'taskStats' => $this->eventMapper->getTaskStats(),
            'agentCount' => count($this->agents),
            'taskCount' => count($this->tasks)
        ];
    }

    /**
     * Stop the current crew execution
     *
     * @return void
     */
    public function stopExecution(): void
    {
        if ($this->isRunning) {
            $this->isRunning = false;
            $this->eventMapper->mapError(
                'execution_stopped',
                'Crew execution stopped by user',
                [
                    'process' => $this->currentProcess,
                    'currentStep' => $this->currentStep
                ]
            );
            $this->eventMapper->complete();
        }
    }
}
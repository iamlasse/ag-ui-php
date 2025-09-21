<?php

declare(strict_types=1);

namespace AGUI\Integrations\LangGraph;

use AGUI\Client\AbstractAgent;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\TextMessageStartEvent;
use AGUI\Core\Events\TextMessageContentEvent;
use AGUI\Core\Events\TextMessageEndEvent;
use AGUI\Core\Events\ToolCallStartEvent;
use AGUI\Core\Events\ToolCallEndEvent;
use AGUI\Core\Events\StateSnapshotEvent;
use AGUI\Core\Events\StateDeltaEvent;
use AGUI\Core\Events\MessagesSnapshotEvent;
use AGUI\Core\Events\RunStartedEvent;
use AGUI\Core\Events\RunFinishedEvent;
use AGUI\Core\Events\RunErrorEvent;
use AGUI\Core\Events\StepStartedEvent;
use AGUI\Core\Events\StepFinishedEvent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;
use AGUI\Core\Types\RunAgentInput;
use AGUI\Core\Types\State;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * LangGraph Agent implementation for AG-UI protocol
 *
 * This agent connects to LangGraph services and translates LangGraph events
 * to AG-UI protocol events for seamless integration.
 *
 * @package AGUI\Integrations\LangGraph
 */
class LangGraphAgent extends AbstractAgent
{
    private Client $httpClient;
    private string $langGraphUrl;
    private string $apiKey;
    private array $config = [];

    /**
     * LangGraphAgent constructor
     *
     * @param array $config Configuration array with keys:
     *                       - langGraphUrl: LangGraph service URL
     *                       - apiKey: API key for authentication
     *                       - threadId: Thread ID for conversation
     *                       - assistantId: Assistant ID
     *                       - debug: Enable debug mode
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->langGraphUrl = $config['langGraphUrl'] ?? 'http://localhost:8000';
        $this->apiKey = $config['apiKey'] ?? '';
        $this->config = $config;

        $this->httpClient = new Client([
            'base_uri' => $this->langGraphUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey ? "Bearer {$this->apiKey}" : '',
            ],
        ]);
    }

    /**
     * Run the agent with the given input
     *
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    protected function run(RunAgentInput $input): \Generator
    {
        try {
            // Emit run started event
            yield new RunStartedEvent([
                'threadId' => $input->threadId,
                'runId' => $input->runId,
                'timestamp' => time(),
            ]);

            // Prepare LangGraph request payload
            $payload = $this->prepareLangGraphPayload($input);

            // Make request to LangGraph service
            $response = $this->httpClient->post('/runs', [
                'json' => $payload,
                'stream' => true,
            ]);

            // Process streaming response
            $stream = $response->getBody();
            while (!$stream->eof()) {
                $line = $stream->readLine();
                if ($line === false || $line === '') {
                    continue;
                }

                // Parse SSE line
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                    if ($data === '[DONE]') {
                        break;
                    }

                    $eventData = json_decode($data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        yield from $this->processLangGraphEvent($eventData, $input);
                    }
                }
            }

            // Emit run finished event
            yield new RunFinishedEvent([
                'threadId' => $input->threadId,
                'runId' => $input->runId,
                'timestamp' => time(),
            ]);
        } catch (RequestException $e) {
            yield new RunErrorEvent([
                'threadId' => $input->threadId,
                'runId' => $input->runId,
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ]);
        } catch (\Exception $e) {
            yield new RunErrorEvent([
                'threadId' => $input->threadId,
                'runId' => $input->runId,
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Prepare payload for LangGraph service
     *
     * @param RunAgentInput $input
     * @return array
     */
    private function prepareLangGraphPayload(RunAgentInput $input): array
    {
        return [
            'thread_id' => $input->threadId,
            'assistant_id' => $this->config['assistantId'] ?? 'default',
            'input' => [
                'messages' => $this->convertMessagesToLangGraph($input->messages),
                'tools' => $this->convertToolsToLangGraph($input->tools),
                'state' => $input->state,
            ],
            'config' => $this->config['langGraphConfig'] ?? [],
            'stream_mode' => ['values'],
        ];
    }

    /**
     * Convert AG-UI messages to LangGraph format
     *
     * @param array $messages
     * @return array
     */
    private function convertMessagesToLangGraph(array $messages): array
    {
        return array_map(function ($message) {
            $langGraphMessage = [
                'type' => $message->role,
                'content' => $message->content ?? '',
            ];

            if ($message->name) {
                $langGraphMessage['name'] = $message->name;
            }

            if ($message->role === 'assistant' && isset($message->toolCalls)) {
                $langGraphMessage['tool_calls'] = array_map(function ($toolCall) {
                    return [
                        'id' => $toolCall->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $toolCall->function->name,
                            'arguments' => $toolCall->function->arguments,
                        ],
                    ];
                }, $message->toolCalls);
            }

            return $langGraphMessage;
        }, $messages);
    }

    /**
     * Convert AG-UI tools to LangGraph format
     *
     * @param array $tools
     * @return array
     */
    private function convertToolsToLangGraph(array $tools): array
    {
        return array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                ],
            ];
        }, $tools);
    }

    /**
     * Process LangGraph event and convert to AG-UI events
     *
     * @param array $eventData
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function processLangGraphEvent(array $eventData, RunAgentInput $input): \Generator
    {
        $eventType = $eventData['event'] ?? '';

        switch ($eventType) {
            case 'messages/partial':
                if (isset($eventData['data']['content'])) {
                    yield from $this->handleTextMessagePartial($eventData['data'], $input);
                }
                break;

            case 'messages/complete':
                if (isset($eventData['data']['content'])) {
                    yield from $this->handleTextMessageComplete($eventData['data'], $input);
                }
                break;

            case 'tool_calls/start':
                if (isset($eventData['data']['tool_calls'])) {
                    yield from $this->handleToolCallsStart($eventData['data'], $input);
                }
                break;

            case 'tool_calls/end':
                if (isset($eventData['data']['tool_calls'])) {
                    yield from $this->handleToolCallsEnd($eventData['data'], $input);
                }
                break;

            case 'state/update':
                if (isset($eventData['data']['state'])) {
                    yield from $this->handleStateUpdate($eventData['data'], $input);
                }
                break;

            case 'step/start':
                if (isset($eventData['data']['step'])) {
                    yield from $this->handleStepStart($eventData['data'], $input);
                }
                break;

            case 'step/end':
                if (isset($eventData['data']['step'])) {
                    yield from $this->handleStepEnd($eventData['data'], $input);
                }
                break;

            default:
                // Handle unknown events as custom events
                yield new BaseEvent([
                    'type' => EventType::CUSTOM,
                    'rawEvent' => $eventData,
                    'timestamp' => time(),
                ]);
                break;
        }
    }

    /**
     * Handle partial text message events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleTextMessagePartial(array $data, RunAgentInput $input): \Generator
    {
        $messageId = $data['message_id'] ?? Uuid::uuid4()->toString();

        if (!isset($this->activeTextMessages[$messageId])) {
            $this->activeTextMessages[$messageId] = true;

            yield new TextMessageStartEvent([
                'messageId' => $messageId,
                'role' => 'assistant',
                'timestamp' => time(),
            ]);
        }

        yield new TextMessageContentEvent([
            'messageId' => $messageId,
            'delta' => $data['content'],
            'timestamp' => time(),
        ]);
    }

    /**
     * Handle complete text message events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleTextMessageComplete(array $data, RunAgentInput $input): \Generator
    {
        $messageId = $data['message_id'] ?? Uuid::uuid4()->toString();

        yield new TextMessageEndEvent([
            'messageId' => $messageId,
            'timestamp' => time(),
        ]);

        unset($this->activeTextMessages[$messageId]);
    }

    /**
     * Handle tool calls start events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleToolCallsStart(array $data, RunAgentInput $input): \Generator
    {
        foreach ($data['tool_calls'] as $toolCall) {
            yield new ToolCallStartEvent([
                'toolCallId' => $toolCall['id'],
                'toolCallName' => $toolCall['function']['name'],
                'parentMessageId' => $data['message_id'] ?? null,
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Handle tool calls end events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleToolCallsEnd(array $data, RunAgentInput $input): \Generator
    {
        foreach ($data['tool_calls'] as $toolCall) {
            yield new ToolCallEndEvent([
                'toolCallId' => $toolCall['id'],
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Handle state update events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleStateUpdate(array $data, RunAgentInput $input): \Generator
    {
        if (isset($data['state']['snapshot'])) {
            yield new StateSnapshotEvent([
                'state' => $data['state']['snapshot'],
                'timestamp' => time(),
            ]);
        }

        if (isset($data['state']['delta'])) {
            yield new StateDeltaEvent([
                'delta' => $data['state']['delta'],
                'timestamp' => time(),
            ]);
        }

        if (isset($data['state']['messages'])) {
            yield new MessagesSnapshotEvent([
                'messages' => $this->convertMessagesFromLangGraph($data['state']['messages']),
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Handle step start events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleStepStart(array $data, RunAgentInput $input): \Generator
    {
        yield new StepStartedEvent([
            'stepId' => $data['step']['id'] ?? Uuid::uuid4()->toString(),
            'stepName' => $data['step']['name'] ?? 'unknown',
            'timestamp' => time(),
        ]);
    }

    /**
     * Handle step end events
     *
     * @param array $data
     * @param RunAgentInput $input
     * @return \Generator<BaseEvent>
     */
    private function handleStepEnd(array $data, RunAgentInput $input): \Generator
    {
        yield new StepFinishedEvent([
            'stepId' => $data['step']['id'] ?? Uuid::uuid4()->toString(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Convert messages from LangGraph format to AG-UI format
     *
     * @param array $messages
     * @return array
     */
    private function convertMessagesFromLangGraph(array $messages): array
    {
        return array_map(function ($message) {
            $aguiMessage = new Message();
            $aguiMessage->id = $message['id'] ?? Uuid::uuid4()->toString();
            $aguiMessage->role = $message['type'];
            $aguiMessage->content = $message['content'] ?? '';

            if (isset($message['name'])) {
                $aguiMessage->name = $message['name'];
            }

            if (isset($message['tool_calls'])) {
                $aguiMessage->toolCalls = array_map(function ($toolCall) {
                    $tool = new Tool();
                    $tool->id = $toolCall['id'];
                    $tool->type = 'function';
                    $tool->function = (object) [
                        'name' => $toolCall['function']['name'],
                        'arguments' => $toolCall['function']['arguments'],
                    ];
                    return $tool;
                }, $message['tool_calls']);
            }

            return $aguiMessage;
        }, $messages);
    }

    /**
     * Get the LangGraph URL
     *
     * @return string
     */
    public function getLangGraphUrl(): string
    {
        return $this->langGraphUrl;
    }

    /**
     * Set the LangGraph URL
     *
     * @param string $url
     * @return self
     */
    public function setLangGraphUrl(string $url): self
    {
        $this->langGraphUrl = $url;
        $this->httpClient = new Client([
            'base_uri' => $this->langGraphUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey ? "Bearer {$this->apiKey}" : '',
            ],
        ]);
        return $this;
    }

    /**
     * Get the API key
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Set the API key
     *
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        $this->httpClient = new Client([
            'base_uri' => $this->langGraphUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey ? "Bearer {$this->apiKey}" : '',
            ],
        ]);
        return $this;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set configuration
     *
     * @param array $config
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}
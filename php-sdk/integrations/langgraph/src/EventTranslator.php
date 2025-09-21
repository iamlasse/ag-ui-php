<?php

declare(strict_types=1);

namespace AGUI\Integrations\LangGraph;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
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
use Ramsey\Uuid\Uuid;

/**
 * Event translator for converting LangGraph events to AG-UI protocol events
 *
 * This class handles the translation between LangGraph's event format
 * and the AG-UI protocol event format.
 *
 * @package AGUI\Integrations\LangGraph
 */
class EventTranslator
{
    /**
     * Active text messages being processed
     *
     * @var array<string, bool>
     */
    private array $activeTextMessages = [];

    /**
     * Active tool calls being processed
     *
     * @var array<string, bool>
     */
    private array $activeToolCalls = [];

    /**
     * Translate LangGraph event to AG-UI event
     *
     * @param array $langGraphEvent
     * @param string $threadId
     * @param string $runId
     * @return BaseEvent|null
     */
    public function translate(array $langGraphEvent, string $threadId, string $runId): ?BaseEvent
    {
        $eventType = $langGraphEvent['event'] ?? '';

        try {
            switch ($eventType) {
                case 'run/start':
                    return $this->translateRunStart($langGraphEvent, $threadId, $runId);

                case 'run/end':
                    return $this->translateRunEnd($langGraphEvent, $threadId, $runId);

                case 'run/error':
                    return $this->translateRunError($langGraphEvent, $threadId, $runId);

                case 'messages/partial':
                    return $this->translateMessagePartial($langGraphEvent, $threadId, $runId);

                case 'messages/complete':
                    return $this->translateMessageComplete($langGraphEvent, $threadId, $runId);

                case 'tool_calls/start':
                    return $this->translateToolCallStart($langGraphEvent, $threadId, $runId);

                case 'tool_calls/end':
                    return $this->translateToolCallEnd($langGraphEvent, $threadId, $runId);

                case 'state/update':
                    return $this->translateStateUpdate($langGraphEvent, $threadId, $runId);

                case 'step/start':
                    return $this->translateStepStart($langGraphEvent, $threadId, $runId);

                case 'step/end':
                    return $this->translateStepEnd($langGraphEvent, $threadId, $runId);

                case 'thinking/start':
                    return $this->translateThinkingStart($langGraphEvent, $threadId, $runId);

                case 'thinking/end':
                    return $this->translateThinkingEnd($langGraphEvent, $threadId, $runId);

                default:
                    return $this->translateCustomEvent($langGraphEvent, $threadId, $runId);
            }
        } catch (\Exception $e) {
            // Log error and return null for untranslatable events
            error_log("Error translating LangGraph event: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Translate run start event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return RunStartedEvent
     */
    private function translateRunStart(array $event, string $threadId, string $runId): RunStartedEvent
    {
        return new RunStartedEvent([
            'threadId' => $threadId,
            'runId' => $runId,
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate run end event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return RunFinishedEvent
     */
    private function translateRunEnd(array $event, string $threadId, string $runId): RunFinishedEvent
    {
        return new RunFinishedEvent([
            'threadId' => $threadId,
            'runId' => $runId,
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate run error event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return RunErrorEvent
     */
    private function translateRunError(array $event, string $threadId, string $runId): RunErrorEvent
    {
        return new RunErrorEvent([
            'threadId' => $threadId,
            'runId' => $runId,
            'error' => $event['data']['error'] ?? 'Unknown error',
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate message partial event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return TextMessageContentEvent|TextMessageStartEvent|null
     */
    private function translateMessagePartial(array $event, string $threadId, string $runId): ?BaseEvent
    {
        $data = $event['data'] ?? [];
        $messageId = $data['message_id'] ?? Uuid::uuid4()->toString();
        $content = $data['content'] ?? '';

        if ($content === '') {
            return null;
        }

        // Start message if not already started
        if (!isset($this->activeTextMessages[$messageId])) {
            $this->activeTextMessages[$messageId] = true;

            return new TextMessageStartEvent([
                'messageId' => $messageId,
                'role' => $data['role'] ?? 'assistant',
                'timestamp' => time(),
            ]);
        }

        // Add content
        return new TextMessageContentEvent([
            'messageId' => $messageId,
            'delta' => $content,
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate message complete event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return TextMessageEndEvent|null
     */
    private function translateMessageComplete(array $event, string $threadId, string $runId): ?BaseEvent
    {
        $data = $event['data'] ?? [];
        $messageId = $data['message_id'] ?? Uuid::uuid4()->toString();

        if (!isset($this->activeTextMessages[$messageId])) {
            return null;
        }

        unset($this->activeTextMessages[$messageId]);

        return new TextMessageEndEvent([
            'messageId' => $messageId,
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate tool call start event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return ToolCallStartEvent|null
     */
    private function translateToolCallStart(array $event, string $threadId, string $runId): ?BaseEvent
    {
        $data = $event['data'] ?? [];
        $toolCalls = $data['tool_calls'] ?? [];

        foreach ($toolCalls as $toolCall) {
            $toolCallId = $toolCall['id'] ?? Uuid::uuid4()->toString();

            if (!isset($this->activeToolCalls[$toolCallId])) {
                $this->activeToolCalls[$toolCallId] = true;

                return new ToolCallStartEvent([
                    'toolCallId' => $toolCallId,
                    'toolCallName' => $toolCall['function']['name'] ?? 'unknown',
                    'parentMessageId' => $data['message_id'] ?? null,
                    'timestamp' => time(),
                ]);
            }
        }

        return null;
    }

    /**
     * Translate tool call end event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return ToolCallEndEvent|null
     */
    private function translateToolCallEnd(array $event, string $threadId, string $runId): ?BaseEvent
    {
        $data = $event['data'] ?? [];
        $toolCalls = $data['tool_calls'] ?? [];

        foreach ($toolCalls as $toolCall) {
            $toolCallId = $toolCall['id'] ?? Uuid::uuid4()->toString();

            if (isset($this->activeToolCalls[$toolCallId])) {
                unset($this->activeToolCalls[$toolCallId]);

                return new ToolCallEndEvent([
                    'toolCallId' => $toolCallId,
                    'timestamp' => time(),
                ]);
            }
        }

        return null;
    }

    /**
     * Translate state update event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return BaseEvent|null
     */
    private function translateStateUpdate(array $event, string $threadId, string $runId): ?BaseEvent
    {
        $data = $event['data'] ?? [];
        $state = $data['state'] ?? [];

        // Priority: snapshot > delta > messages
        if (isset($state['snapshot'])) {
            return new StateSnapshotEvent([
                'state' => $state['snapshot'],
                'timestamp' => time(),
            ]);
        }

        if (isset($state['delta'])) {
            return new StateDeltaEvent([
                'delta' => $state['delta'],
                'timestamp' => time(),
            ]);
        }

        if (isset($state['messages'])) {
            return new MessagesSnapshotEvent([
                'messages' => $this->convertMessagesFromLangGraph($state['messages']),
                'timestamp' => time(),
            ]);
        }

        return null;
    }

    /**
     * Translate step start event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return StepStartedEvent
     */
    private function translateStepStart(array $event, string $threadId, string $runId): StepStartedEvent
    {
        $data = $event['data'] ?? [];
        $step = $data['step'] ?? [];

        return new StepStartedEvent([
            'stepId' => $step['id'] ?? Uuid::uuid4()->toString(),
            'stepName' => $step['name'] ?? 'unknown',
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate step end event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return StepFinishedEvent
     */
    private function translateStepEnd(array $event, string $threadId, string $runId): StepFinishedEvent
    {
        $data = $event['data'] ?? [];
        $step = $data['step'] ?? [];

        return new StepFinishedEvent([
            'stepId' => $step['id'] ?? Uuid::uuid4()->toString(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate thinking start event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return BaseEvent
     */
    private function translateThinkingStart(array $event, string $threadId, string $runId): BaseEvent
    {
        return new BaseEvent([
            'type' => EventType::CUSTOM,
            'rawEvent' => array_merge($event, [
                'translated_type' => 'thinking_start',
                'thread_id' => $threadId,
                'run_id' => $runId,
            ]),
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate thinking end event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return BaseEvent
     */
    private function translateThinkingEnd(array $event, string $threadId, string $runId): BaseEvent
    {
        return new BaseEvent([
            'type' => EventType::CUSTOM,
            'rawEvent' => array_merge($event, [
                'translated_type' => 'thinking_end',
                'thread_id' => $threadId,
                'run_id' => $runId,
            ]),
            'timestamp' => time(),
        ]);
    }

    /**
     * Translate custom event
     *
     * @param array $event
     * @param string $threadId
     * @param string $runId
     * @return BaseEvent
     */
    private function translateCustomEvent(array $event, string $threadId, string $runId): BaseEvent
    {
        return new BaseEvent([
            'type' => EventType::CUSTOM,
            'rawEvent' => array_merge($event, [
                'thread_id' => $threadId,
                'run_id' => $runId,
            ]),
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
            $aguiMessage->role = $message['type'] ?? 'assistant';
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
     * Clear active messages and tool calls
     *
     * @return void
     */
    public function clearActiveEvents(): void
    {
        $this->activeTextMessages = [];
        $this->activeToolCalls = [];
    }

    /**
     * Get active text messages
     *
     * @return array<string, bool>
     */
    public function getActiveTextMessages(): array
    {
        return $this->activeTextMessages;
    }

    /**
     * Get active tool calls
     *
     * @return array<string, bool>
     */
    public function getActiveToolCalls(): array
    {
        return $this->activeToolCalls;
    }
}
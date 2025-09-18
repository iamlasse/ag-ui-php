<?php

declare(strict_types=1);

namespace AGUI\Core\Observable;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
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

/**
 * Stream processing utilities for EventObservable streams
 *
 * @package AGUI\Core\Observable
 */
class StreamProcessor
{
    /**
     * Create a stream processor for handling event streams
     */
    public function __construct()
    {
    }

    /**
     * Process a complete run lifecycle (RUN_STARTED to RUN_FINISHED)
     *
     * @param EventObservable $observable
     * @param callable $onRunStart
     * @param callable $onRunEnd
     * @param callable $onEvent
     * @return EventObservable
     */
    public function processRunLifecycle(
        EventObservable $observable,
        callable $onRunStart,
        callable $onRunEnd,
        callable $onEvent
    ): EventObservable {
        return $observable
            ->filterByEventType(EventType::RUN_STARTED)
            ->map(function (RunStarted $event) use ($onRunStart) {
                $onRunStart($event);
                return $event;
            })
            ->mergeEvents(
                $observable
                    ->filter(function (BaseEvent $event) {
                        return !$event->getType()->isLifecycleEvent();
                    })
                    ->map(function (BaseEvent $event) use ($onEvent) {
                        $onEvent($event);
                        return $event;
                    })
            )
            ->mergeEvents(
                $observable
                    ->filterByEventType(EventType::RUN_FINISHED)
                    ->map(function (RunFinished $event) use ($onRunEnd) {
                        $onRunEnd($event);
                        return $event;
                    })
            );
    }

    /**
     * Aggregate text message chunks into complete messages
     *
     * @param EventObservable $observable
     * @return EventObservable
     */
    public function aggregateTextMessages(EventObservable $observable): EventObservable
    {
        $messageBuffers = [];

        return $observable->filter(function (BaseEvent $event) {
            return $event->getType()->isTextMessageEvent();
        })->map(function (BaseEvent $event) use (&$messageBuffers) {
            $runId = $event->getRunId() ?? 'default';

            switch ($event->getType()) {
                case EventType::TEXT_MESSAGE_START:
                    $messageBuffers[$runId] = [
                        'id' => $event->getId(),
                        'content' => '',
                        'timestamp' => $event->getTimestamp(),
                        'metadata' => $event->getMetadata()
                    ];
                    break;

                case EventType::TEXT_MESSAGE_CHUNK:
                    if (isset($messageBuffers[$runId])) {
                        /** @var TextMessageChunk $event */
                        $messageBuffers[$runId]['content'] .= $event->getEventData()['content'] ?? '';
                    }
                    break;

                case EventType::TEXT_MESSAGE_END:
                    if (isset($messageBuffers[$runId])) {
                        $completeMessage = $messageBuffers[$runId];
                        unset($messageBuffers[$runId]);
                        return $completeMessage;
                    }
                    break;
            }

            return null;
        })->filter(function ($message) {
            return $message !== null;
        });
    }

    /**
     * Aggregate tool call chunks into complete tool calls
     *
     * @param EventObservable $observable
     * @return EventObservable
     */
    public function aggregateToolCalls(EventObservable $observable): EventObservable
    {
        $toolCallBuffers = [];

        return $observable->filter(function (BaseEvent $event) {
            return $event->getType()->isToolCallEvent();
        })->map(function (BaseEvent $event) use (&$toolCallBuffers) {
            $runId = $event->getRunId() ?? 'default';

            switch ($event->getType()) {
                case EventType::TOOL_CALL_START:
                    $toolCallBuffers[$runId][$event->getId()] = [
                        'id' => $event->getId(),
                        'tool' => $event->getEventData()['tool'] ?? null,
                        'input' => $event->getEventData()['input'] ?? '',
                        'output' => '',
                        'timestamp' => $event->getTimestamp(),
                        'metadata' => $event->getMetadata()
                    ];
                    break;

                case EventType::TOOL_CALL_CHUNK:
                    if (isset($toolCallBuffers[$runId][$event->getId()])) {
                        /** @var ToolCallChunk $event */
                        $toolCallBuffers[$runId][$event->getId()]['output'] .= $event->getEventData()['content'] ?? '';
                    }
                    break;

                case EventType::TOOL_CALL_END:
                    if (isset($toolCallBuffers[$runId][$event->getId()])) {
                        $completeToolCall = $toolCallBuffers[$runId][$event->getId()];
                        unset($toolCallBuffers[$runId][$event->getId()]);
                        return $completeToolCall;
                    }
                    break;
            }

            return null;
        })->filter(function ($toolCall) {
            return $toolCall !== null;
        });
    }

    /**
     * Process state changes and compute final state
     *
     * @param EventObservable $observable
     * @return EventObservable
     */
    public function processStateChanges(EventObservable $observable): EventObservable
    {
        $currentState = null;

        return $observable->filter(function (BaseEvent $event) {
            return $event->getType()->isStateEvent();
        })->map(function (BaseEvent $event) use (&$currentState) {
            switch ($event->getType()) {
                case EventType::STATE_SNAPSHOT:
                    /** @var StateSnapshot $event */
                    $currentState = $event->getEventData()['state'] ?? [];
                    return [
                        'type' => 'snapshot',
                        'state' => $currentState,
                        'timestamp' => $event->getTimestamp()
                    ];

                case EventType::STATE_DELTA:
                    /** @var StateDelta $event */
                    if ($currentState !== null) {
                        $patches = $event->getEventData()['patches'] ?? [];
                        $currentState = $this->applyPatches($currentState, $patches);
                    }
                    return [
                        'type' => 'delta',
                        'state' => $currentState,
                        'patches' => $patches,
                        'timestamp' => $event->getTimestamp()
                    ];

                case EventType::MESSAGES_SNAPSHOT:
                    /** @var MessagesSnapshot $event */
                    return [
                        'type' => 'messages',
                        'messages' => $event->getEventData()['messages'] ?? [],
                        'timestamp' => $event->getTimestamp()
                    ];

                default:
                    return null;
            }
        })->filter(function ($state) {
            return $state !== null;
        });
    }

    /**
     * Apply JSON patches to state
     *
     * @param array $state
     * @param array $patches
     * @return array
     */
    private function applyPatches(array $state, array $patches): array
    {
        foreach ($patches as $patch) {
            $path = $patch['path'] ?? '';
            $op = $patch['op'] ?? 'add';
            $value = $patch['value'] ?? null;

            switch ($op) {
                case 'add':
                    $this->addValueAtPath($state, $path, $value);
                    break;
                case 'remove':
                    $this->removeValueAtPath($state, $path);
                    break;
                case 'replace':
                    $this->replaceValueAtPath($state, $path, $value);
                    break;
                case 'move':
                    $from = $patch['from'] ?? '';
                    $movedValue = $this->getValueAtPath($state, $from);
                    $this->removeValueAtPath($state, $from);
                    $this->addValueAtPath($state, $path, $movedValue);
                    break;
                case 'copy':
                    $from = $patch['from'] ?? '';
                    $copiedValue = $this->getValueAtPath($state, $from);
                    $this->addValueAtPath($state, $path, $copiedValue);
                    break;
            }
        }

        return $state;
    }

    /**
     * Get value at JSON path
     *
     * @param array $data
     * @param string $path
     * @return mixed
     */
    private function getValueAtPath(array &$data, string $path): mixed
    {
        $parts = $this->parsePath($path);
        $current = &$data;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = &$current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Add value at JSON path
     *
     * @param array $data
     * @param string $path
     * @param mixed $value
     */
    private function addValueAtPath(array &$data, string $path, mixed $value): void
    {
        $parts = $this->parsePath($path);
        $current = &$data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                if ($part === '') {
                    // Array append
                    $current[] = $value;
                } else {
                    $current[$part] = $value;
                }
            } else {
                if (!isset($current[$part])) {
                    $current[$part] = is_numeric($parts[$i + 1]) ? [] : [];
                }
                $current = &$current[$part];
            }
        }
    }

    /**
     * Remove value at JSON path
     *
     * @param array $data
     * @param string $path
     */
    private function removeValueAtPath(array &$data, string $path): void
    {
        $parts = $this->parsePath($path);
        $current = &$data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                unset($current[$part]);
            } else {
                if (isset($current[$part])) {
                    $current = &$current[$part];
                } else {
                    return;
                }
            }
        }
    }

    /**
     * Replace value at JSON path
     *
     * @param array $data
     * @param string $path
     * @param mixed $value
     */
    private function replaceValueAtPath(array &$data, string $path, mixed $value): void
    {
        $parts = $this->parsePath($path);
        $current = &$data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (isset($current[$part])) {
                    $current = &$current[$part];
                } else {
                    return;
                }
            }
        }
    }

    /**
     * Parse JSON path into parts
     *
     * @param string $path
     * @return array
     */
    private function parsePath(string $path): array
    {
        if (empty($path) || $path === '/') {
            return [];
        }

        $parts = explode('/', substr($path, 1));
        return array_map(function ($part) {
            return str_replace('~1', '/', str_replace('~0', '~', $part));
        }, $parts);
    }

    /**
     * Create event statistics processor
     *
     * @param EventObservable $observable
     * @return EventObservable
     */
    public function createEventStatistics(EventObservable $observable): EventObservable
    {
        $stats = [
            'total_events' => 0,
            'by_type' => [],
            'by_category' => [],
            'by_run' => []
        ];

        return $observable->map(function (BaseEvent $event) use (&$stats) {
            $stats['total_events']++;
            $eventType = $event->getType()->value;
            $category = $event->getType()->getCategory();
            $runId = $event->getRunId() ?? 'default';

            $stats['by_type'][$eventType] = ($stats['by_type'][$eventType] ?? 0) + 1;
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
            $stats['by_run'][$runId] = ($stats['by_run'][$runId] ?? 0) + 1;

            return [
                'event' => $event,
                'stats' => $stats
            ];
        });
    }

    /**
     * Debounce events by type
     *
     * @param EventObservable $observable
     * @param int $timeWindow
     * @return EventObservable
     */
    public function debounceByType(EventObservable $observable, int $timeWindow): EventObservable
    {
        $debouncers = [];

        return $observable->map(function (BaseEvent $event) use (&$debouncers, $timeWindow) {
            $eventType = $event->getType()->value;

            if (!isset($debouncers[$eventType])) {
                $debouncers[$eventType] = [
                    'last_emitted' => 0,
                    'pending_events' => []
                ];
            }

            $debouncers[$eventType]['pending_events'][] = $event;

            $currentTime = microtime(true) * 1000;
            if ($currentTime - $debouncers[$eventType]['last_emitted'] >= $timeWindow) {
                $eventsToEmit = $debouncers[$eventType]['pending_events'];
                $debouncers[$eventType]['pending_events'] = [];
                $debouncers[$eventType]['last_emitted'] = $currentTime;

                return $eventsToEmit;
            }

            return null;
        })->filter(function ($events) {
            return $events !== null && !empty($events);
        });
    }

    /**
     * Batch events by time window
     *
     * @param EventObservable $observable
     * @param int $windowSize
     * @return EventObservable
     */
    public function batchByTimeWindow(EventObservable $observable, int $windowSize): EventObservable
    {
        $batch = [];
        $windowStart = null;

        return $observable->map(function (BaseEvent $event) use (&$batch, &$windowStart, $windowSize) {
            $currentTime = microtime(true) * 1000;

            if ($windowStart === null) {
                $windowStart = $currentTime;
            }

            if ($currentTime - $windowStart >= $windowSize) {
                $currentBatch = $batch;
                $batch = [$event];
                $windowStart = $currentTime;
                return $currentBatch;
            } else {
                $batch[] = $event;
                return null;
            }
        })->filter(function ($batch) {
            return $batch !== null && !empty($batch);
        });
    }
}
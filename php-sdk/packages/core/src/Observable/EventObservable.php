<?php

declare(strict_types=1);

namespace AGUI\Core\Observable;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
use Rx\Observable as RxObservable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;
use Rx\DisposableInterface;
use Rx\Disposable\CallbackDisposable;

/**
 * EventObservable class that extends Rx\Observable for streaming AG-UI events
 *
 * @package AGUI\Core\Observable
 */
class EventObservable extends RxObservable
{
    private Subject $subject;
    private ?DisposableInterface $subscription = null;

    /**
     * EventObservable constructor
     */
    public function __construct()
    {
        $this->subject = new Subject();
        parent::__construct($this->createSubscribeFunction());
    }

    /**
     * Create the subscribe function for the observable
     *
     * @return callable
     */
    private function createSubscribeFunction(): callable
    {
        return function (ObserverInterface $observer): DisposableInterface {
            return $this->subject->subscribe($observer);
        };
    }

    /**
     * Emit an event to the observable stream
     *
     * @param BaseEvent $event The event to emit
     * @return $this
     */
    public function emitEvent(BaseEvent $event): self
    {
        $this->subject->onNext($event);
        return $this;
    }

    /**
     * Emit an error to the observable stream
     *
     * @param \Throwable $error The error to emit
     * @return $this
     */
    public function emitError(\Throwable $error): self
    {
        $this->subject->onError($error);
        return $this;
    }

    /**
     * Complete the observable stream
     *
     * @return $this
     */
    public function complete(): self
    {
        $this->subject->onCompleted();
        return $this;
    }

    /**
     * Filter events by type
     *
     * @param EventType $type The event type to filter by
     * @return EventObservable
     */
    public function filterByEventType(EventType $type): EventObservable
    {
        return $this->filter(function (BaseEvent $event) use ($type) {
            return $event->getType() === $type;
        });
    }

    /**
     * Filter events by category
     *
     * @param string $category The category to filter by
     * @return EventObservable
     */
    public function filterByCategory(string $category): EventObservable
    {
        return $this->filter(function (BaseEvent $event) use ($category) {
            return $event->getType()->getCategory() === $category;
        });
    }

    /**
     * Filter events by run ID
     *
     * @param string $runId The run ID to filter by
     * @return EventObservable
     */
    public function filterByRunId(string $runId): EventObservable
    {
        return $this->filter(function (BaseEvent $event) use ($runId) {
            return $event->getRunId() === $runId;
        });
    }

    /**
     * Map events to their data
     *
     * @return EventObservable
     */
    public function mapToEventData(): EventObservable
    {
        return $this->map(function (BaseEvent $event) {
            return $event->getEventData();
        });
    }

    /**
     * Map events to their full data
     *
     * @return EventObservable
     */
    public function mapToFullData(): EventObservable
    {
        return $this->map(function (BaseEvent $event) {
            return $event->getFullData();
        });
    }

    /**
     * Map events to JSON
     *
     * @return EventObservable
     */
    public function mapToJson(): EventObservable
    {
        return $this->map(function (BaseEvent $event) {
            return $event->toJson();
        });
    }

    /**
     * Buffer events by time window
     *
     * @param int $timeWindow Time window in milliseconds
     * @return EventObservable
     */
    public function bufferTime(int $timeWindow): EventObservable
    {
        return $this->bufferWithTime($timeWindow);
    }

    /**
     * Get events count
     *
     * @return EventObservable
     */
    public function countEvents(): EventObservable
    {
        return $this->count();
    }

    /**
     * Take specific number of events
     *
     * @param int $count Number of events to take
     * @return EventObservable
     */
    public function takeEvents(int $count): EventObservable
    {
        return $this->take($count);
    }

    /**
     * Skip specific number of events
     *
     * @param int $count Number of events to skip
     * @return EventObservable
     */
    public function skipEvents(int $count): EventObservable
    {
        return $this->skip($count);
    }

    /**
     * Debounce events by time
     *
     * @param int $time Time in milliseconds
     * @return EventObservable
     */
    public function debounce(int $time): EventObservable
    {
        return $this->debounceTime($time);
    }

    /**
     * Throttle events by time
     *
     * @param int $time Time in milliseconds
     * @return EventObservable
     */
    public function throttle(int $time): EventObservable
    {
        return $this->throttleTime($time);
    }

    /**
     * Get distinct events by ID
     *
     * @return EventObservable
     */
    public function distinctEvents(): EventObservable
    {
        return $this->distinct(function (BaseEvent $event) {
            return $event->getId();
        });
    }

    /**
     * Merge with another EventObservable
     *
     * @param EventObservable $other The other observable to merge
     * @return EventObservable
     */
    public function mergeEvents(EventObservable $other): EventObservable
    {
        return $this->merge($other);
    }

    /**
     * Concatenate with another EventObservable
     *
     * @param EventObservable $other The other observable to concatenate
     * @return EventObservable
     */
    public function concatEvents(EventObservable $other): EventObservable
    {
        return $this->concat($other);
    }

    /**
     * Subscribe to events with specific handlers
     *
     * @param callable|null $onNext Handler for next events
     * @param callable|null $onError Handler for errors
     * @param callable|null $onCompleted Handler for completion
     * @return DisposableInterface
     */
    public function subscribeEvents(
        ?callable $onNext = null,
        ?callable $onError = null,
        ?callable $onCompleted = null
    ): DisposableInterface {
        return $this->subscribe($onNext, $onError, $onCompleted);
    }

    /**
     * Create EventObservable from array of events
     *
     * @param array<BaseEvent> $events Array of events
     * @return EventObservable
     */
    public static function fromEvents(array $events): EventObservable
    {
        $observable = new self();
        foreach ($events as $event) {
            $observable->emitEvent($event);
        }
        $observable->complete();
        return $observable;
    }

    /**
     * Create EventObservable from event generator
     *
     * @param callable $generator Function that generates events
     * @return EventObservable
     */
    public static function fromGenerator(callable $generator): EventObservable
    {
        $observable = new self();

        // Generate events in a way that doesn't block
        $events = $generator();
        foreach ($events as $event) {
            $observable->emitEvent($event);
        }
        $observable->complete();

        return $observable;
    }

    /**
     * Create EventObservable from promise
     *
     * @param \React\Promise\PromiseInterface $promise
     * @return EventObservable
     */
    public static function fromPromise(\React\Promise\PromiseInterface $promise): EventObservable
    {
        $observable = new self();

        $promise->then(
            function ($result) use ($observable) {
                if ($result instanceof BaseEvent) {
                    $observable->emitEvent($result);
                }
                $observable->complete();
            },
            function ($error) use ($observable) {
                $observable->emitError($error instanceof \Throwable ? $error : new \RuntimeException($error));
            }
        );

        return $observable;
    }

    /**
     * Create a periodic event emitter
     *
     * @param int $interval Interval in milliseconds
     * @param callable $eventGenerator Function to generate events
     * @return EventObservable
     */
    public static function interval(int $interval, callable $eventGenerator): EventObservable
    {
        $observable = new self();

        $timer = \React\EventLoop\Loop::addPeriodicTimer($interval / 1000, function () use ($observable, $eventGenerator) {
            try {
                $event = $eventGenerator();
                if ($event instanceof BaseEvent) {
                    $observable->emitEvent($event);
                }
            } catch (\Throwable $error) {
                $observable->emitError($error);
            }
        });

        // Clean up timer when observable is disposed
        $observable->subscribe(
            null,
            null,
            function () use ($timer) {
                \React\EventLoop\Loop::cancelTimer($timer);
            }
        );

        return $observable;
    }

    /**
     * Get the underlying subject
     *
     * @return Subject
     */
    public function getSubject(): Subject
    {
        return $this->subject;
    }

    /**
     * Check if the observable is completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->subject->isDisposed();
    }

    /**
     * Get current subscriber count
     *
     * @return int
     */
    public function getSubscriberCount(): int
    {
        // This is a rough estimate as RxPHP doesn't provide direct access to subscriber count
        return 0; // Would need to extend Subject to track this
    }
}
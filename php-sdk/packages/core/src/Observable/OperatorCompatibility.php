<?php

declare(strict_types=1);

namespace AGUI\Core\Observable;

use AGUI\Core\Events\BaseEvent;
use AGUI\Core\Events\EventType;
use Rx\Observable;
use Rx\OperatorInterface;
use Rx\ObserverInterface;

/**
 * RxPHP Operator Compatibility Layer for EventObservable
 *
 * @package AGUI\Core\Observable
 */
class OperatorCompatibility
{
    /**
     * Map operator for transforming events
     *
     * @param callable $transformer
     * @return OperatorInterface
     */
    public static function map(callable $transformer): OperatorInterface
    {
        return new class($transformer) implements OperatorInterface {
            public function __construct(private readonly callable $transformer) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        try {
                            $result = ($this->transformer)($value);
                            $observer->onNext($result);
                        } catch (\Throwable $error) {
                            $observer->onError($error);
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Filter operator for filtering events
     *
     * @param callable $predicate
     * @return OperatorInterface
     */
    public static function filter(callable $predicate): OperatorInterface
    {
        return new class($predicate) implements OperatorInterface {
            public function __construct(private readonly callable $predicate) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        try {
                            if (($this->predicate)($value)) {
                                $observer->onNext($value);
                            }
                        } catch (\Throwable $error) {
                            $observer->onError($error);
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Event type filter operator
     *
     * @param EventType $type
     * @return OperatorInterface
     */
    public static function filterByEventType(EventType $type): OperatorInterface
    {
        return self::filter(function (BaseEvent $event) use ($type) {
            return $event->getType() === $type;
        });
    }

    /**
     * Event category filter operator
     *
     * @param string $category
     * @return OperatorInterface
     */
    public static function filterByCategory(string $category): OperatorInterface
    {
        return self::filter(function (BaseEvent $event) use ($category) {
            return $event->getType()->getCategory() === $category;
        });
    }

    /**
     * Run ID filter operator
     *
     * @param string $runId
     * @return OperatorInterface
     */
    public static function filterByRunId(string $runId): OperatorInterface
    {
        return self::filter(function (BaseEvent $event) use ($runId) {
            return $event->getRunId() === $runId;
        });
    }

    /**
     * Buffer with time operator
     *
     * @param int $timeWindow
     * @return OperatorInterface
     */
    public static function bufferWithTime(int $timeWindow): OperatorInterface
    {
        return new class($timeWindow) implements OperatorInterface {
            private array $buffer = [];
            private ?\React\EventLoop\TimerInterface $timer = null;

            public function __construct(private readonly int $timeWindow) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                $this->timer = \React\EventLoop\Loop::addPeriodicTimer($this->timeWindow / 1000, function () use ($observer) {
                    if (!empty($this->buffer)) {
                        $observer->onNext($this->buffer);
                        $this->buffer = [];
                    }
                });

                return $observable->subscribe(
                    function ($value) use ($observer) {
                        $this->buffer[] = $value;
                    },
                    function ($error) use ($observer) {
                        if ($this->timer) {
                            \React\EventLoop\Loop::cancelTimer($this->timer);
                        }
                        $observer->onError($error);
                    },
                    function () use ($observer) {
                        if ($this->timer) {
                            \React\EventLoop\Loop::cancelTimer($this->timer);
                        }
                        if (!empty($this->buffer)) {
                            $observer->onNext($this->buffer);
                        }
                        $observer->onCompleted();
                    }
                );
            }
        };
    }

    /**
     * Debounce time operator
     *
     * @param int $time
     * @return OperatorInterface
     */
    public static function debounceTime(int $time): OperatorInterface
    {
        return new class($time) implements OperatorInterface {
            private ?\React\EventLoop\TimerInterface $timer = null;
            private $lastValue = null;

            public function __construct(private readonly int $time) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        $this->lastValue = $value;

                        if ($this->timer) {
                            \React\EventLoop\Loop::cancelTimer($this->timer);
                        }

                        $this->timer = \React\EventLoop\Loop::addTimer($this->time / 1000, function () use ($observer) {
                            if ($this->lastValue !== null) {
                                $observer->onNext($this->lastValue);
                                $this->lastValue = null;
                            }
                        });
                    },
                    function ($error) use ($observer) {
                        if ($this->timer) {
                            \React\EventLoop\Loop::cancelTimer($this->timer);
                        }
                        $observer->onError($error);
                    },
                    function () use ($observer) {
                        if ($this->timer) {
                            \React\EventLoop\Loop::cancelTimer($this->timer);
                        }
                        if ($this->lastValue !== null) {
                            $observer->onNext($this->lastValue);
                        }
                        $observer->onCompleted();
                    }
                );
            }
        };
    }

    /**
     * Throttle time operator
     *
     * @param int $time
     * @return OperatorInterface
     */
    public static function throttleTime(int $time): OperatorInterface
    {
        return new class($time) implements OperatorInterface {
            private bool $throttled = false;

            public function __construct(private readonly int $time) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        if (!$this->throttled) {
                            $observer->onNext($value);
                            $this->throttled = true;

                            \React\EventLoop\Loop::addTimer($this->time / 1000, function () {
                                $this->throttled = false;
                            });
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Distinct operator
     *
     * @param callable|null $keySelector
     * @return OperatorInterface
     */
    public static function distinct(?callable $keySelector = null): OperatorInterface
    {
        return new class($keySelector) implements OperatorInterface {
            private array $seenKeys = [];

            public function __construct(private readonly ?callable $keySelector) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        try {
                            $key = $this->keySelector ? ($this->keySelector)($value) : $value;

                            if (!in_array($key, $this->seenKeys, true)) {
                                $this->seenKeys[] = $key;
                                $observer->onNext($value);
                            }
                        } catch (\Throwable $error) {
                            $observer->onError($error);
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Take operator
     *
     * @param int $count
     * @return OperatorInterface
     */
    public static function take(int $count): OperatorInterface
    {
        return new class($count) implements OperatorInterface {
            private int $taken = 0;

            public function __construct(private readonly int $count) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        if ($this->taken < $this->count) {
                            $observer->onNext($value);
                            $this->taken++;

                            if ($this->taken >= $this->count) {
                                $observer->onCompleted();
                            }
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Skip operator
     *
     * @param int $count
     * @return OperatorInterface
     */
    public static function skip(int $count): OperatorInterface
    {
        return new class($count) implements OperatorInterface {
            private int $skipped = 0;

            public function __construct(private readonly int $count) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        if ($this->skipped < $this->count) {
                            $this->skipped++;
                        } else {
                            $observer->onNext($value);
                        }
                    },
                    [$observer, 'onError'],
                    [$observer, 'onCompleted']
                );
            }
        };
    }

    /**
     * Count operator
     *
     * @return OperatorInterface
     */
    public static function count(): OperatorInterface
    {
        return new class() implements OperatorInterface {
            private int $counter = 0;

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    function ($value) use ($observer) {
                        $this->counter++;
                    },
                    [$observer, 'onError'],
                    function () use ($observer) {
                        $observer->onNext($this->counter);
                        $observer->onCompleted();
                    }
                );
            }
        };
    }

    /**
     * Merge operator for combining observables
     *
     * @param Observable $other
     * @return OperatorInterface
     */
    public static function merge(Observable $other): OperatorInterface
    {
        return new class($other) implements OperatorInterface {
            public function __construct(private readonly Observable $other) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                $subscriptions = [];

                $subscriptions[] = $observable->subscribe(
                    [$observer, 'onNext'],
                    [$observer, 'onError'],
                    function () use ($observer, &$subscriptions) {
                        unset($subscriptions[0]);
                        if (empty($subscriptions)) {
                            $observer->onCompleted();
                        }
                    }
                );

                $subscriptions[] = $this->other->subscribe(
                    [$observer, 'onNext'],
                    [$observer, 'onError'],
                    function () use ($observer, &$subscriptions) {
                        unset($subscriptions[1]);
                        if (empty($subscriptions)) {
                            $observer->onCompleted();
                        }
                    }
                );

                return new \Rx\Disposable\CallbackDisposable(function () use (&$subscriptions) {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription) {
                            $subscription->dispose();
                        }
                    }
                });
            }
        };
    }

    /**
     * Concat operator for sequential observables
     *
     * @param Observable $other
     * @return OperatorInterface
     */
    public static function concat(Observable $other): OperatorInterface
    {
        return new class($other) implements OperatorInterface {
            public function __construct(private readonly Observable $other) {}

            public function __invoke(Observable $observable, ObserverInterface $observer): \Rx\DisposableInterface
            {
                return $observable->subscribe(
                    [$observer, 'onNext'],
                    [$observer, 'onError'],
                    function () use ($observer) {
                        $this->other->subscribe(
                            [$observer, 'onNext'],
                            [$observer, 'onError'],
                            [$observer, 'onCompleted']
                        );
                    }
                );
            }
        };
    }
}
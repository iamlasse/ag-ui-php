<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\RunStarted;
use AGUI\Core\Events\RunFinished;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for lifecycle events
 *
 * @package AGUI\Core\Tests\Events
 */
class LifecycleEventsTest extends TestCase
{
    /**
     * Test RunStarted creation
     */
    public function testRunStartedCreation(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'test-agent', ['input' => 'data'], ['config' => 'value']);

        $this->assertInstanceOf(RunStarted::class, $event);
        $this->assertEquals(EventType::RUN_STARTED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertEquals('test-agent', $event->getAgentName());
        $this->assertEquals(['input' => 'data'], $event->getInput());
        $this->assertEquals(['config' => 'value'], $event->getConfig());
    }

    /**
     * Test RunStarted with minimal parameters
     */
    public function testRunStartedMinimal(): void
    {
        $event = EventFactory::createRunStarted('run-123');

        $this->assertInstanceOf(RunStarted::class, $event);
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertNull($event->getAgentName());
        $this->assertNull($event->getInput());
        $this->assertNull($event->getConfig());
    }

    /**
     * Test RunStarted array conversion
     */
    public function testRunStartedToArray(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'test-agent', ['input' => 'data'], ['config' => 'value']);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('run_started', $array['type']);
        $this->assertArrayHasKey('runId', $array);
    }

    /**
     * Test RunStarted event data
     */
    public function testRunStartedEventData(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'test-agent', ['input' => 'data'], ['config' => 'value']);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('agentName', $eventData);
        $this->assertArrayHasKey('input', $eventData);
        $this->assertArrayHasKey('config', $eventData);
        $this->assertEquals('test-agent', $eventData['agentName']);
        $this->assertEquals(['input' => 'data'], $eventData['input']);
        $this->assertEquals(['config' => 'value'], $eventData['config']);
    }

    /**
     * Test RunStarted getInputValue
     */
    public function testRunStartedGetInputValue(): void
    {
        $event = EventFactory::createRunStarted('run-123', null, ['key1' => 'value1', 'key2' => 'value2']);

        $this->assertEquals('value1', $event->getInputValue('key1'));
        $this->assertEquals('value2', $event->getInputValue('key2'));
        $this->assertEquals('default', $event->getInputValue('nonexistent', 'default'));
    }

    /**
     * Test RunStarted getConfigValue
     */
    public function testRunStartedGetConfigValue(): void
    {
        $event = EventFactory::createRunStarted('run-123', null, null, ['config1' => 'value1', 'config2' => 'value2']);

        $this->assertEquals('value1', $event->getConfigValue('config1'));
        $this->assertEquals('value2', $event->getConfigValue('config2'));
        $this->assertEquals('default', $event->getConfigValue('nonexistent', 'default'));
    }

    /**
     * Test RunStarted withAgentName
     */
    public function testRunStartedWithAgentName(): void
    {
        $event = EventFactory::createRunStarted('run-123', 'original-agent');
        $newEvent = $event->withAgentName('new-agent');

        $this->assertEquals('new-agent', $newEvent->getAgentName());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunStarted withInput
     */
    public function testRunStartedWithInput(): void
    {
        $event = EventFactory::createRunStarted('run-123');
        $newEvent = $event->withInput(['new' => 'input']);

        $this->assertEquals(['new' => 'input'], $newEvent->getInput());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunStarted withConfig
     */
    public function testRunStartedWithConfig(): void
    {
        $event = EventFactory::createRunStarted('run-123');
        $newEvent = $event->withConfig(['new' => 'config']);

        $this->assertEquals(['new' => 'config'], $newEvent->getConfig());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunFinished creation
     */
    public function testRunFinishedCreation(): void
    {
        $event = EventFactory::createRunFinished('run-123', true, 'result data', null, 1000);

        $this->assertInstanceOf(RunFinished::class, $event);
        $this->assertEquals(EventType::RUN_FINISHED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertTrue($event->isSuccess());
        $this->assertEquals('result data', $event->getResult());
        $this->assertEquals(1000, $event->getDuration());
    }

    /**
     * Test RunFinished with failure
     */
    public function testRunFinishedWithFailure(): void
    {
        $event = EventFactory::createRunFinished('run-123', false, null, 'error message');

        $this->assertInstanceOf(RunFinished::class, $event);
        $this->assertEquals(EventType::RUN_FINISHED, $event->getType());
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertFalse($event->isSuccess());
        $this->assertEquals('error message', $event->getError());
        $this->assertNull($event->getResult());
    }

    /**
     * Test RunFinished with minimal parameters
     */
    public function testRunFinishedMinimal(): void
    {
        $event = EventFactory::createRunFinished('run-123', true);

        $this->assertInstanceOf(RunFinished::class, $event);
        $this->assertEquals('run-123', $event->getRunId());
        $this->assertTrue($event->isSuccess());
        $this->assertNull($event->getResult());
        $this->assertNull($event->getError());
        $this->assertNull($event->getDuration());
    }

    /**
     * Test RunFinished array conversion
     */
    public function testRunFinishedToArray(): void
    {
        $event = EventFactory::createRunFinished('run-123', true, 'result data', 'error message', 1000);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('run_finished', $array['type']);
        $this->assertArrayHasKey('runId', $array);
    }

    /**
     * Test RunFinished event data
     */
    public function testRunFinishedEventData(): void
    {
        $event = EventFactory::createRunFinished('run-123', true, 'result data', 'error message', 1000);
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('success', $eventData);
        $this->assertArrayHasKey('result', $eventData);
        $this->assertArrayHasKey('error', $eventData);
        $this->assertArrayHasKey('duration', $eventData);
        $this->assertTrue($eventData['success']);
        $this->assertEquals('result data', $eventData['result']);
        $this->assertEquals('error message', $eventData['error']);
        $this->assertEquals(1000, $eventData['duration']);
    }

    /**
     * Test RunFinished withSuccess
     */
    public function testRunFinishedWithSuccess(): void
    {
        $event = EventFactory::createRunFinished('run-123', true);
        $newEvent = $event->withSuccess(false);

        $this->assertFalse($newEvent->isSuccess());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunFinished withResult
     */
    public function testRunFinishedWithResult(): void
    {
        $event = EventFactory::createRunFinished('run-123', true);
        $newEvent = $event->withResult('new result');

        $this->assertEquals('new result', $newEvent->getResult());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunFinished withError
     */
    public function testRunFinishedWithError(): void
    {
        $event = EventFactory::createRunFinished('run-123', true);
        $newEvent = $event->withError('new error');

        $this->assertEquals('new error', $newEvent->getError());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test RunFinished withDuration
     */
    public function testRunFinishedWithDuration(): void
    {
        $event = EventFactory::createRunFinished('run-123', true);
        $newEvent = $event->withDuration(5000);

        $this->assertEquals(5000, $newEvent->getDuration());
        $this->assertEquals($event->getRunId(), $newEvent->getRunId());
    }

    /**
     * Test validation for empty run ID in RunStarted
     */
    public function testRunStartedValidationForEmptyRunId(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createRunStarted('');
    }

    /**
     * Test validation for empty run ID in RunFinished
     */
    public function testRunFinishedValidationForEmptyRunId(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createRunFinished('', true);
    }

    /**
     * Test validation for negative duration in RunFinished
     */
    public function testRunFinishedValidationForNegativeDuration(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createRunFinished('run-123', true, null, null, -1);
    }
}
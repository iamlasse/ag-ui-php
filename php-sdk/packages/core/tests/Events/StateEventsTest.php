<?php

declare(strict_types=1);

namespace AGUI\Core\Tests\Events;

use AGUI\Core\Events\EventFactory;
use AGUI\Core\Events\EventType;
use AGUI\Core\Events\StateSnapshot;
use AGUI\Core\Events\StateDelta;
use AGUI\Core\Events\MessagesSnapshot;
use AGUI\Core\Types\UserMessage;
use AGUI\Core\Types\SystemMessage;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for state events
 *
 * @package AGUI\Core\Tests\Events
 */
class StateEventsTest extends TestCase
{
    /**
     * Test StateSnapshot creation
     */
    public function testStateSnapshotCreation(): void
    {
        $state = ['key1' => 'value1', 'key2' => 'value2', 'nested' => ['a' => 'b']];
        $event = EventFactory::createStateSnapshot($state, 'state-123', 'run-123');

        $this->assertInstanceOf(StateSnapshot::class, $event);
        $this->assertEquals(EventType::STATE_SNAPSHOT, $event->getType());
        $this->assertEquals($state, $event->getState());
        $this->assertEquals('state-123', $event->getStateId());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test StateSnapshot with minimal parameters
     */
    public function testStateSnapshotMinimal(): void
    {
        $state = ['key' => 'value'];
        $event = EventFactory::createStateSnapshot($state);

        $this->assertInstanceOf(StateSnapshot::class, $event);
        $this->assertEquals($state, $event->getState());
        $this->assertNull($event->getStateId());
        $this->assertNull($event->getRunId());
    }

    /**
     * Test StateSnapshot array conversion
     */
    public function testStateSnapshotToArray(): void
    {
        $state = ['key' => 'value'];
        $event = EventFactory::createStateSnapshot($state, 'state-123');
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('state_snapshot', $array['type']);
    }

    /**
     * Test StateSnapshot event data
     */
    public function testStateSnapshotEventData(): void
    {
        $state = ['key' => 'value'];
        $event = EventFactory::createStateSnapshot($state, 'state-123');
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('state', $eventData);
        $this->assertArrayHasKey('stateId', $eventData);
        $this->assertEquals($state, $eventData['state']);
        $this->assertEquals('state-123', $eventData['stateId']);
    }

    /**
     * Test StateSnapshot getStateValue
     */
    public function testStateSnapshotGetStateValue(): void
    {
        $state = ['key1' => 'value1', 'key2' => 'value2', 'nested' => ['a' => 'b']];
        $event = EventFactory::createStateSnapshot($state);

        $this->assertEquals('value1', $event->getStateValue('key1'));
        $this->assertEquals('value2', $event->getStateValue('key2'));
        $this->assertEquals('default', $event->getStateValue('nonexistent', 'default'));
        $this->assertEquals(['a' => 'b'], $event->getStateValue('nested'));
    }

    /**
     * Test StateSnapshot hasStateKey
     */
    public function testStateSnapshotHasStateKey(): void
    {
        $state = ['key1' => 'value1', 'key2' => null];
        $event = EventFactory::createStateSnapshot($state);

        $this->assertTrue($event->hasStateKey('key1'));
        $this->assertTrue($event->hasStateKey('key2'));
        $this->assertFalse($event->hasStateKey('nonexistent'));
    }

    /**
     * Test StateSnapshot withState
     */
    public function testStateSnapshotWithState(): void
    {
        $state1 = ['key1' => 'value1'];
        $state2 = ['key2' => 'value2'];
        $event = EventFactory::createStateSnapshot($state1);
        $newEvent = $event->withState($state2);

        $this->assertEquals($state2, $newEvent->getState());
        $this->assertEquals($event->getId(), $newEvent->getId());
    }

    /**
     * Test StateSnapshot withStateId
     */
    public function testStateSnapshotWithStateId(): void
    {
        $event = EventFactory::createStateSnapshot(['key' => 'value']);
        $newEvent = $event->withStateId('new-state-id');

        $this->assertEquals('new-state-id', $newEvent->getStateId());
        $this->assertEquals($event->getState(), $newEvent->getState());
    }

    /**
     * Test StateDelta creation
     */
    public function testStateDeltaCreation(): void
    {
        $patches = [
            ['op' => 'add', 'path' => '/key', 'value' => 'value'],
            ['op' => 'replace', 'path' => '/existing', 'value' => 'new'],
            ['op' => 'remove', 'path' => '/toRemove']
        ];
        $event = EventFactory::createStateDelta($patches, 'state-123', 'state-122', 'run-123');

        $this->assertInstanceOf(StateDelta::class, $event);
        $this->assertEquals(EventType::STATE_DELTA, $event->getType());
        $this->assertEquals($patches, $event->getPatches());
        $this->assertEquals('state-123', $event->getStateId());
        $this->assertEquals('state-122', $event->getPreviousStateId());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test StateDelta with minimal parameters
     */
    public function testStateDeltaMinimal(): void
    {
        $patches = [['op' => 'add', 'path' => '/key', 'value' => 'value']];
        $event = EventFactory::createStateDelta($patches);

        $this->assertInstanceOf(StateDelta::class, $event);
        $this->assertEquals($patches, $event->getPatches());
        $this->assertNull($event->getStateId());
        $this->assertNull($event->getPreviousStateId());
    }

    /**
     * Test StateDelta array conversion
     */
    public function testStateDeltaToArray(): void
    {
        $patches = [['op' => 'add', 'path' => '/key', 'value' => 'value']];
        $event = EventFactory::createStateDelta($patches);
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('state_delta', $array['type']);
    }

    /**
     * Test StateDelta event data
     */
    public function testStateDeltaEventData(): void
    {
        $patches = [['op' => 'add', 'path' => '/key', 'value' => 'value']];
        $event = EventFactory::createStateDelta($patches, 'state-123', 'state-122');
        $eventData = $event->getEventData();

        $this->assertArrayHasKey('patches', $eventData);
        $this->assertArrayHasKey('stateId', $eventData);
        $this->assertArrayHasKey('previousStateId', $eventData);
        $this->assertEquals($patches, $eventData['patches']);
        $this->assertEquals('state-123', $eventData['stateId']);
        $this->assertEquals('state-122', $eventData['previousStateId']);
    }

    /**
     * Test StateDelta getPatchCount
     */
    public function testStateDeltaGetPatchCount(): void
    {
        $patches = [
            ['op' => 'add', 'path' => '/key1', 'value' => 'value1'],
            ['op' => 'replace', 'path' => '/key2', 'value' => 'value2'],
            ['op' => 'remove', 'path' => '/key3']
        ];
        $event = EventFactory::createStateDelta($patches);

        $this->assertEquals(3, $event->getPatchCount());
    }

    /**
     * Test StateDelta getPatchesByOperation
     */
    public function testStateDeltaGetPatchesByOperation(): void
    {
        $patches = [
            ['op' => 'add', 'path' => '/key1', 'value' => 'value1'],
            ['op' => 'add', 'path' => '/key2', 'value' => 'value2'],
            ['op' => 'replace', 'path' => '/key3', 'value' => 'value3'],
            ['op' => 'remove', 'path' => '/key4']
        ];
        $event = EventFactory::createStateDelta($patches);

        $addPatches = $event->getPatchesByOperation('add');
        $replacePatches = $event->getPatchesByOperation('replace');
        $removePatches = $event->getPatchesByOperation('remove');
        $testPatches = $event->getPatchesByOperation('test');

        $this->assertCount(2, $addPatches);
        $this->assertCount(1, $replacePatches);
        $this->assertCount(1, $removePatches);
        $this->assertCount(0, $testPatches);
    }

    /**
     * Test StateDelta withPatches
     */
    public function testStateDeltaWithPatches(): void
    {
        $patches1 = [['op' => 'add', 'path' => '/key1', 'value' => 'value1']];
        $patches2 = [['op' => 'add', 'path' => '/key2', 'value' => 'value2']];
        $event = EventFactory::createStateDelta($patches1);
        $newEvent = $event->withPatches($patches2);

        $this->assertEquals($patches2, $newEvent->getPatches());
        $this->assertEquals($event->getId(), $newEvent->getId());
    }

    /**
     * Test StateDelta withStateId
     */
    public function testStateDeltaWithStateId(): void
    {
        $patches = [['op' => 'add', 'path' => '/key', 'value' => 'value']];
        $event = EventFactory::createStateDelta($patches);
        $newEvent = $event->withStateId('new-state-id');

        $this->assertEquals('new-state-id', $newEvent->getStateId());
        $this->assertEquals($event->getPatches(), $newEvent->getPatches());
    }

    /**
     * Test StateDelta withPreviousStateId
     */
    public function testStateDeltaWithPreviousStateId(): void
    {
        $patches = [['op' => 'add', 'path' => '/key', 'value' => 'value']];
        $event = EventFactory::createStateDelta($patches);
        $newEvent = $event->withPreviousStateId('previous-state-id');

        $this->assertEquals('previous-state-id', $newEvent->getPreviousStateId());
        $this->assertEquals($event->getPatches(), $newEvent->getPatches());
    }

    /**
     * Test MessagesSnapshot creation
     */
    public function testMessagesSnapshotCreation(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there')
        ];
        $event = EventFactory::createMessagesSnapshot($messages, 'snapshot-123', 2, 'run-123');

        $this->assertInstanceOf(MessagesSnapshot::class, $event);
        $this->assertEquals(EventType::MESSAGES_SNAPSHOT, $event->getType());
        $this->assertEquals($messages, $event->getMessages());
        $this->assertEquals('snapshot-123', $event->getSnapshotId());
        $this->assertEquals(2, $event->getTotalMessages());
        $this->assertEquals('run-123', $event->getRunId());
    }

    /**
     * Test MessagesSnapshot with minimal parameters
     */
    public function testMessagesSnapshotMinimal(): void
    {
        $messages = [new UserMessage('msg-1', 'Hello')];
        $event = EventFactory::createMessagesSnapshot($messages);

        $this->assertInstanceOf(MessagesSnapshot::class, $event);
        $this->assertEquals($messages, $event->getMessages());
        $this->assertNull($event->getSnapshotId());
        $this->assertEquals(1, $event->getTotalMessages()); // Returns count when totalMessages is null
    }

    /**
     * Test MessagesSnapshot getTotalMessages fallback
     */
    public function testMessagesSnapshotGetTotalMessagesFallback(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there'),
            new UserMessage('msg-3', 'How are you?')
        ];
        $event = EventFactory::createMessagesSnapshot($messages);

        $this->assertEquals(3, $event->getTotalMessages());
    }

    /**
     * Test MessagesSnapshot getMessage
     */
    public function testMessagesSnapshotGetMessage(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there')
        ];
        $event = EventFactory::createMessagesSnapshot($messages);

        $this->assertEquals($messages[0], $event->getMessage(0));
        $this->assertEquals($messages[1], $event->getMessage(1));
        $this->assertNull($event->getMessage(2));
    }

    /**
     * Test MessagesSnapshot getMessagesByRole
     */
    public function testMessagesSnapshotGetMessagesByRole(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there'),
            new SystemMessage('msg-3', 'System message')
        ];
        $event = EventFactory::createMessagesSnapshot($messages);

        $userMessages = $event->getMessagesByRole('user');
        $assistantMessages = $event->getMessagesByRole('assistant');
        $systemMessages = $event->getMessagesByRole('system');

        $this->assertCount(2, $userMessages);
        $this->assertCount(0, $assistantMessages);
        $this->assertCount(1, $systemMessages);
    }

    /**
     * Test MessagesSnapshot getLastMessage
     */
    public function testMessagesSnapshotGetLastMessage(): void
    {
        $messages = [
            new UserMessage('msg-1', 'Hello'),
            new UserMessage('msg-2', 'Hi there')
        ];
        $event = EventFactory::createMessagesSnapshot($messages);

        $lastMessage = $event->getLastMessage();
        $this->assertEquals($messages[1], $lastMessage);
    }

    /**
     * Test MessagesSnapshot getLastMessage with empty messages
     */
    public function testMessagesSnapshotGetLastMessageEmpty(): void
    {
        $event = EventFactory::createMessagesSnapshot([]);

        $this->assertNull($event->getLastMessage());
    }

    /**
     * Test MessagesSnapshot withMessages
     */
    public function testMessagesSnapshotWithMessages(): void
    {
        $messages1 = [new UserMessage('msg-1', 'Hello')];
        $messages2 = [new UserMessage('msg-2', 'Hi there')];
        $event = EventFactory::createMessagesSnapshot($messages1);
        $newEvent = $event->withMessages($messages2);

        $this->assertEquals($messages2, $newEvent->getMessages());
        $this->assertEquals($event->getId(), $newEvent->getId());
    }

    /**
     * Test MessagesSnapshot withSnapshotId
     */
    public function testMessagesSnapshotWithSnapshotId(): void
    {
        $messages = [new UserMessage('msg-1', 'Hello')];
        $event = EventFactory::createMessagesSnapshot($messages);
        $newEvent = $event->withSnapshotId('new-snapshot-id');

        $this->assertEquals('new-snapshot-id', $newEvent->getSnapshotId());
        $this->assertEquals($event->getMessages(), $newEvent->getMessages());
    }

    /**
     * Test validation for invalid JSON Patch operations
     */
    public function testValidationForInvalidJsonPatchOperations(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        $patches = [
            ['op' => 'invalid', 'path' => '/key', 'value' => 'value']
        ];
        EventFactory::createStateDelta($patches);
    }

    /**
     * Test validation for empty patches
     */
    public function testValidationForEmptyPatches(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        EventFactory::createStateDelta([]);
    }

    /**
     * Test validation for missing operation in patch
     */
    public function testValidationForMissingOperation(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        $patches = [
            ['path' => '/key', 'value' => 'value']
        ];
        EventFactory::createStateDelta($patches);
    }

    /**
     * Test validation for missing path in patch
     */
    public function testValidationForMissingPath(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);

        $patches = [
            ['op' => 'add', 'value' => 'value']
        ];
        EventFactory::createStateDelta($patches);
    }
}
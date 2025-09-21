<?php

declare(strict_types=1);

namespace AGUI\Integrations\LangGraph\Tests;

use AGUI\Integrations\LangGraph\LangGraphAgent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Exception\RequestException;

/**
 * LangGraphAgent Test Suite
 *
 * @package AGUI\Integrations\LangGraph\Tests
 */
class LangGraphAgentTest extends TestCase
{
    private LangGraphAgent $agent;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'langGraphUrl' => 'http://localhost:8000',
            'apiKey' => 'test-api-key',
            'assistantId' => 'test-assistant',
            'threadId' => 'test-thread-' . uniqid(),
            'debug' => false,
        ];

        $this->agent = new LangGraphAgent($this->config);
    }

    public function testAgentCreation(): void
    {
        $this->assertInstanceOf(LangGraphAgent::class, $this->agent);
        $this->assertEquals($this->config['langGraphUrl'], $this->agent->getLangGraphUrl());
        $this->assertEquals($this->config['apiKey'], $this->agent->getApiKey());
    }

    public function testAgentConfig(): void
    {
        $newConfig = [
            'langGraphUrl' => 'http://localhost:9000',
            'apiKey' => 'new-api-key',
        ];

        $this->agent->setLangGraphUrl($newConfig['langGraphUrl'])
                  ->setApiKey($newConfig['apiKey'])
                  ->setConfig($newConfig);

        $this->assertEquals($newConfig['langGraphUrl'], $this->agent->getLangGraphUrl());
        $this->assertEquals($newConfig['apiKey'], $this->agent->getApiKey());
    }

    public function testConvertMessagesToLangGraph(): void
    {
        $messages = [
            new Message([
                'id' => 'msg-1',
                'role' => 'user',
                'content' => 'Hello, world!',
                'name' => 'test-user',
            ]),
            new Message([
                'id' => 'msg-2',
                'role' => 'assistant',
                'content' => 'Hello back!',
                'toolCalls' => [
                    (object) [
                        'id' => 'tool-1',
                        'type' => 'function',
                        'function' => (object) [
                            'name' => 'test_function',
                            'arguments' => '{"param": "value"}',
                        ]
                    ]
                ]
            ])
        ];

        $reflection = new \ReflectionClass($this->agent);
        $method = $reflection->getMethod('convertMessagesToLangGraph');
        $method->setAccessible(true);

        $result = $method->invoke($this->agent, $messages);

        $this->assertCount(2, $result);
        $this->assertEquals('user', $result[0]['type']);
        $this->assertEquals('Hello, world!', $result[0]['content']);
        $this->assertEquals('test-user', $result[0]['name']);

        $this->assertEquals('assistant', $result[1]['type']);
        $this->assertEquals('Hello back!', $result[1]['content']);
        $this->assertArrayHasKey('tool_calls', $result[1]);
        $this->assertEquals('test_function', $result[1]['tool_calls'][0]['function']['name']);
    }

    public function testConvertToolsToLangGraph(): void
    {
        $tools = [
            $this->createTestTool('weather', 'Get weather information'),
            $this->createTestTool('calculator', 'Perform calculations'),
        ];

        $reflection = new \ReflectionClass($this->agent);
        $method = $reflection->getMethod('convertToolsToLangGraph');
        $method->setAccessible(true);

        $result = $method->invoke($this->agent, $tools);

        $this->assertCount(2, $result);
        $this->assertEquals('function', $result[0]['type']);
        $this->assertEquals('weather', $result[0]['function']['name']);
        $this->assertEquals('Get weather information', $result[0]['function']['description']);
    }

    public function testPrepareLangGraphPayload(): void
    {
        $messages = [
            new Message([
                'id' => 'msg-1',
                'role' => 'user',
                'content' => 'Test message',
            ])
        ];

        $tools = [$this->createTestTool('test', 'Test tool')];

        $input = new \AGUI\Core\Types\RunAgentInput();
        $input->threadId = 'test-thread';
        $input->runId = 'test-run';
        $input->messages = $messages;
        $input->tools = $tools;
        $input->state = (object) ['key' => 'value'];
        $input->context = [];
        $input->forwardedProps = [];

        $reflection = new \ReflectionClass($this->agent);
        $method = $reflection->getMethod('prepareLangGraphPayload');
        $method->setAccessible(true);

        $result = $method->invoke($this->agent, $input);

        $this->assertEquals('test-thread', $result['thread_id']);
        $this->assertEquals('test-assistant', $result['assistant_id']);
        $this->assertArrayHasKey('input', $result);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('stream_mode', $result);
    }

    public function testEventTranslationHandling(): void
    {
        $reflection = new \ReflectionClass($this->agent);
        $method = $reflection->getMethod('processLangGraphEvent');
        $method->setAccessible(true);

        $input = new \AGUI\Core\Types\RunAgentInput();
        $input->threadId = 'test-thread';
        $input->runId = 'test-run';

        // Test message partial event
        $eventData = [
            'event' => 'messages/partial',
            'data' => [
                'content' => 'Hello',
                'message_id' => 'msg-123'
            ]
        ];

        $events = iterator_to_array($method->invoke($this->agent, $eventData, $input));
        $this->assertNotEmpty($events);
        $this->assertContainsOnlyInstancesOf(\AGUI\Core\Events\BaseEvent::class, $events);
    }

    public function testRunErrorHandling(): void
    {
        // Mock HTTP client to throw an exception
        $mockHandler = new MockHandler([
            new RequestException('Connection error', new \GuzzleHttp\Psr7\Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Replace the agent's HTTP client
        $reflection = new \ReflectionClass($this->agent);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->agent, $client);

        $input = new \AGUI\Core\Types\RunAgentInput();
        $input->threadId = 'test-thread';
        $input->runId = 'test-run';
        $input->messages = [];
        $input->tools = [];
        $input->state = new \stdClass();
        $input->context = [];
        $input->forwardedProps = [];

        $events = iterator_to_array($this->agent->run($input));

        $this->assertNotEmpty($events);
        $errorEvent = null;
        foreach ($events as $event) {
            if ($event->type === \AGUI\Core\Events\EventType::RUN_ERROR) {
                $errorEvent = $event;
                break;
            }
        }

        $this->assertNotNull($errorEvent);
        $this->assertStringContainsString('Connection error', $errorEvent->error);
    }

    public function testAgentCloning(): void
    {
        $this->agent->addMessage(new Message([
            'id' => 'msg-1',
            'role' => 'user',
            'content' => 'Test message',
        ]));

        $cloned = $this->agent->clone();

        $this->assertInstanceOf(LangGraphAgent::class, $cloned);
        $this->assertEquals($this->agent->threadId, $cloned->threadId);
        $this->assertEquals($this->agent->description, $cloned->description);
        $this->assertCount(1, $cloned->messages);
        $this->assertEquals('Test message', $cloned->messages[0]->content);
    }

    public function testMessageManagement(): void
    {
        $message1 = new Message([
            'id' => 'msg-1',
            'role' => 'user',
            'content' => 'First message',
        ]);

        $message2 = new Message([
            'id' => 'msg-2',
            'role' => 'assistant',
            'content' => 'Second message',
        ]);

        $this->agent->addMessage($message1);
        $this->assertCount(1, $this->agent->messages);

        $this->agent->addMessages([$message2]);
        $this->assertCount(2, $this->agent->messages);

        $this->agent->setMessages([$message1]);
        $this->assertCount(1, $this->agent->messages);
        $this->assertEquals('First message', $this->agent->messages[0]->content);
    }

    public function testStateManagement(): void
    {
        $state1 = (object) ['key1' => 'value1'];
        $state2 = (object) ['key2' => 'value2'];

        $this->agent->setState($state1);
        $this->assertEquals($state1, $this->agent->state);

        $this->agent->setState($state2);
        $this->assertEquals($state2, $this->agent->state);
    }

    public function testSubscriberManagement(): void
    {
        $callCount = 0;
        $subscriber = [
            'onNewMessage' => function ($params) use (&$callCount) {
                $callCount++;
            }
        ];

        $subscription = $this->agent->subscribe($subscriber);

        $this->assertCount(1, $this->agent->subscribers);

        // Test unsubscribe
        $subscription->unsubscribe();
        $this->assertCount(0, $this->agent->subscribers);
    }

    private function createTestTool(string $name, string $description): Tool
    {
        $tool = new Tool();
        $tool->name = $name;
        $tool->description = $description;
        $tool->parameters = (object) [
            'type' => 'object',
            'properties' => (object) [],
            'required' => []
        ];
        return $tool;
    }
}
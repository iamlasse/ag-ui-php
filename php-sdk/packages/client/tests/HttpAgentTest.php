<?php

declare(strict_types=1);

namespace AGUI\Tests\Client;

use AGUI\Client\HttpAgent;
use AGUI\Client\Transport\TransportFactory;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Observable\EventObservable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for HttpAgent
 *
 * @package AGUI\Tests\Client
 */
class HttpAgentTest extends TestCase
{
    private HttpAgent $agent;
    private TransportFactory $transportFactory;

    protected function setUp(): void
    {
        $httpClient = new Client();
        $httpFactory = new HttpFactory();
        
        $this->transportFactory = new TransportFactory(
            $httpClient,
            $httpFactory,
            $httpFactory,
            new NullLogger()
        );

        $this->agent = new HttpAgent(
            $httpClient,
            $httpFactory,
            $httpFactory,
            $this->transportFactory,
            new NullLogger()
        );
    }

    public function testAgentInitialization(): void
    {
        $this->assertFalse($this->agent->isRunning());
        $this->assertNotEmpty($this->agent->getId());
        $this->assertIsArray($this->agent->getConfig());
    }

    public function testAgentConfigDefaults(): void
    {
        $config = $this->agent->getConfig();
        
        $this->assertEquals('sse', $config['default_transport']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals(3, $config['retry_attempts']);
        $this->assertArrayHasKey('headers', $config);
    }

    public function testRunConfigCreation(): void
    {
        $config = RunConfig::create('https://example.com/stream');
        
        $this->assertEquals('https://example.com/stream', $config->getEndpoint());
        $this->assertNotEmpty($config->getRunId());
        $this->assertEquals(30, $config->getTimeout());
    }

    public function testRunConfigWithOptions(): void
    {
        $config = RunConfig::create('https://example.com/stream', [
            'sessionId' => 'test-session',
            'userId' => 'test-user',
            'transportType' => 'websocket',
            'timeout' => 60
        ]);

        $this->assertEquals('test-session', $config->getSessionId());
        $this->assertEquals('test-user', $config->getUserId());
        $this->assertEquals('websocket', $config->getTransportType());
        $this->assertEquals(60, $config->getTimeout());
    }

    public function testTransportFactoryCreation(): void
    {
        $this->assertContains('sse', $this->transportFactory->getSupportedTypes());
        $this->assertContains('websocket', $this->transportFactory->getSupportedTypes());
        $this->assertContains('http-binary', $this->transportFactory->getSupportedTypes());
        
        $this->assertTrue($this->transportFactory->isSupported('sse'));
        $this->assertFalse($this->transportFactory->isSupported('unknown'));
    }

    public function testTransportFactoryRecommendations(): void
    {
        $this->assertEquals('websocket', $this->transportFactory->getRecommendedType('ws://example.com'));
        $this->assertEquals('websocket', $this->transportFactory->getRecommendedType('wss://example.com'));
        $this->assertEquals('sse', $this->transportFactory->getRecommendedType('https://example.com/events'));
        $this->assertEquals('http-binary', $this->transportFactory->getRecommendedType('https://example.com/binary'));
        $this->assertEquals('sse', $this->transportFactory->getRecommendedType('https://example.com'));
    }

    /**
     * @group mock-server
     * This test requires a running mock server
     */
    public function testHttpAgentWithMockServer(): void
    {
        $this->markTestSkipped('Mock server integration tests require external setup');
        
        // Example test that would work with a mock server:
        /*
        $config = RunConfig::create('http://localhost:8080/stream', [
            'transportType' => 'sse'
        ]);

        $observable = $this->agent->run($config);
        $this->assertInstanceOf(EventObservable::class, $observable);
        $this->assertTrue($this->agent->isRunning());

        // Collect events for a short time
        $events = [];
        $subscription = $observable->subscribe(function ($event) use (&$events) {
            $events[] = $event;
        });

        usleep(1000000); // Wait 1 second
        $subscription->dispose();
        
        $this->agent->stop();
        $this->assertFalse($this->agent->isRunning());
        */
    }

    public function testAgentStop(): void
    {
        $this->assertFalse($this->agent->isRunning());
        
        // Stop should be safe to call when not running
        $this->agent->stop();
        $this->assertFalse($this->agent->isRunning());
    }

    public function testRunConfigValidation(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);
        RunConfig::create(''); // Empty endpoint should fail
    }

    public function testRunConfigInvalidTransport(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);
        RunConfig::create('https://example.com', [
            'transportType' => 'invalid-transport'
        ]);
    }

    public function testRunConfigTimeout(): void
    {
        $this->expectException(\AGUI\Core\Validation\ValidationException::class);
        RunConfig::create('https://example.com', [
            'timeout' => 500 // Too high
        ]);
    }

    public function testSendWithoutConnection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active transport connection');
        
        $this->agent->send('test data');
    }

    public function testRunTwice(): void
    {
        // This would require mocking the transport to avoid actual network calls
        $this->markTestSkipped('Requires transport mocking');
        
        /*
        $config = RunConfig::create('https://example.com');
        $this->agent->run($config);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Agent is already running');
        $this->agent->run($config);
        */
    }
}

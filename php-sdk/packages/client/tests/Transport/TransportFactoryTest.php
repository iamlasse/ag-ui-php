<?php

declare(strict_types=1);

namespace AGUI\Tests\Client\Transport;

use AGUI\Client\Transport\TransportFactory;
use AGUI\Client\Transport\TransportInterface;
use AGUI\Client\Transport\SSETransport;
use AGUI\Client\Transport\WebSocketTransport;
use AGUI\Client\Transport\HttpBinaryTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for TransportFactory
 *
 * @package AGUI\Tests\Client\Transport
 */
class TransportFactoryTest extends TestCase
{
    private TransportFactory $factory;

    protected function setUp(): void
    {
        $httpClient = new Client();
        $httpFactory = new HttpFactory();
        
        $this->factory = new TransportFactory(
            $httpClient,
            $httpFactory,
            $httpFactory,
            new NullLogger()
        );
    }

    public function testCreateSSETransport(): void
    {
        $transport = $this->factory->create('sse');
        
        $this->assertInstanceOf(SSETransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertEquals('sse', $transport->getType());
    }

    public function testCreateWebSocketTransport(): void
    {
        $transport = $this->factory->create('websocket');
        
        $this->assertInstanceOf(WebSocketTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertEquals('websocket', $transport->getType());
    }

    public function testCreateHttpBinaryTransport(): void
    {
        $transport = $this->factory->create('http-binary');
        
        $this->assertInstanceOf(HttpBinaryTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertEquals('http-binary', $transport->getType());
    }

    public function testCreateUnsupportedTransport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported transport type: unknown');
        
        $this->factory->create('unknown');
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->factory->getSupportedTypes();
        
        $this->assertIsArray($types);
        $this->assertContains('sse', $types);
        $this->assertContains('websocket', $types);
        $this->assertContains('http-binary', $types);
        $this->assertCount(3, $types);
    }

    public function testIsSupported(): void
    {
        $this->assertTrue($this->factory->isSupported('sse'));
        $this->assertTrue($this->factory->isSupported('websocket'));
        $this->assertTrue($this->factory->isSupported('http-binary'));
        $this->assertFalse($this->factory->isSupported('unknown'));
    }

    public function testGetTransportClass(): void
    {
        $this->assertEquals(SSETransport::class, $this->factory->getTransportClass('sse'));
        $this->assertEquals(WebSocketTransport::class, $this->factory->getTransportClass('websocket'));
        $this->assertEquals(HttpBinaryTransport::class, $this->factory->getTransportClass('http-binary'));
        $this->assertNull($this->factory->getTransportClass('unknown'));
    }

    public function testRegisterCustomTransport(): void
    {
        // Create a mock transport class
        $mockTransport = new class implements TransportInterface {
            public function connect(string $endpoint, array $options = []): void {}
            public function send(string $data): void {}
            public function stream(\AGUI\Core\Types\RunConfig $config): \AGUI\Core\Observable\EventObservable {
                return new \AGUI\Core\Observable\EventObservable();
            }
            public function disconnect(): void {}
            public function isConnected(): bool { return false; }
            public function getType(): string { return 'mock'; }
            public function getConfig(): array { return []; }
        };
        
        $className = get_class($mockTransport);
        $this->factory->registerTransport('mock', $className);
        
        $this->assertTrue($this->factory->isSupported('mock'));
        $this->assertEquals($className, $this->factory->getTransportClass('mock'));
        
        $transport = $this->factory->create('mock');
        $this->assertInstanceOf($className, $transport);
    }

    public function testUnregisterTransport(): void
    {
        $this->assertTrue($this->factory->isSupported('sse'));
        
        $this->factory->unregisterTransport('sse');
        
        $this->assertFalse($this->factory->isSupported('sse'));
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->create('sse');
    }

    public function testCreateMultiple(): void
    {
        $configs = [
            'sse' => ['timeout' => 30],
            'websocket' => ['ping_interval' => 30],
            'http-binary' => ['compression' => 'gzip']
        ];
        
        $transports = $this->factory->createMultiple($configs);
        
        $this->assertCount(3, $transports);
        $this->assertInstanceOf(SSETransport::class, $transports['sse']);
        $this->assertInstanceOf(WebSocketTransport::class, $transports['websocket']);
        $this->assertInstanceOf(HttpBinaryTransport::class, $transports['http-binary']);
    }

    public function testCreateWithFallback(): void
    {
        $fallbackTypes = ['unknown', 'sse', 'websocket'];
        
        $transport = $this->factory->createWithFallback($fallbackTypes);
        
        // Should create SSE transport since 'unknown' fails but 'sse' succeeds
        $this->assertInstanceOf(SSETransport::class, $transport);
    }

    public function testCreateWithFallbackAllFail(): void
    {
        // Temporarily unregister all transports to make them fail
        $this->factory->unregisterTransport('sse');
        $this->factory->unregisterTransport('websocket');
        $this->factory->unregisterTransport('http-binary');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create transport with any fallback type');
        
        $this->factory->createWithFallback(['sse', 'websocket']);
    }

    public function testGetRecommendedType(): void
    {
        $this->assertEquals('websocket', $this->factory->getRecommendedType('ws://example.com'));
        $this->assertEquals('websocket', $this->factory->getRecommendedType('wss://secure.example.com'));
        $this->assertEquals('sse', $this->factory->getRecommendedType('https://example.com/sse'));
        $this->assertEquals('sse', $this->factory->getRecommendedType('https://example.com/events'));
        $this->assertEquals('http-binary', $this->factory->getRecommendedType('https://example.com/binary'));
        $this->assertEquals('sse', $this->factory->getRecommendedType('https://example.com/api'));
        $this->assertEquals('sse', $this->factory->getRecommendedType('file://local/path'));
    }

    public function testTransportConfiguration(): void
    {
        $config = [
            'timeout' => 60,
            'custom_option' => 'value'
        ];
        
        $transport = $this->factory->create('sse', $config);
        $transportConfig = $transport->getConfig();
        
        $this->assertEquals(60, $transportConfig['timeout']);
        $this->assertEquals('value', $transportConfig['custom_option']);
    }
}

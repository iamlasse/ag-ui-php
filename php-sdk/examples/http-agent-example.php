<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AGUI\Client\HttpAgent;
use AGUI\Client\Transport\TransportFactory;
use AGUI\Core\Types\RunConfig;
use AGUI\Core\Events\BaseEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup logging
$logger = new Logger('http-agent-example');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create HTTP client and factories
$httpClient = new Client([
    'timeout' => 30,
    'verify' => false // Only for development
]);
$httpFactory = new HttpFactory();

// Create transport factory
$transportFactory = new TransportFactory(
    $httpClient,
    $httpFactory,
    $httpFactory,
    $logger
);

// Create HTTP Agent
$agent = new HttpAgent(
    $httpClient,
    $httpFactory,
    $httpFactory,
    $transportFactory,
    $logger,
    [
        'default_transport' => 'sse',
        'timeout' => 60,
        'retry_attempts' => 5
    ]
);

echo "AG-UI HTTP Agent Example\n";
echo "========================\n\n";

// Example 1: Server-Sent Events (SSE) Transport
echo "Example 1: SSE Transport\n";
echo "------------------------\n";

try {
    $sseConfig = RunConfig::create('https://api.example.com/stream/events', [
        'sessionId' => 'demo-session-' . uniqid(),
        'userId' => 'demo-user',
        'transportType' => 'sse',
        'headers' => [
            'Authorization' => 'Bearer your-token-here',
            'X-Client-Version' => '1.0.0'
        ]
    ]);

    echo "Configuration:\n";
    echo "  Endpoint: " . $sseConfig->getEndpoint() . "\n";
    echo "  Run ID: " . $sseConfig->getRunId() . "\n";
    echo "  Transport: " . $sseConfig->getTransportType() . "\n";
    echo "  Session ID: " . $sseConfig->getSessionId() . "\n\n";

    // Note: This would connect to a real server
    echo "To run this example with a real server:\n";
    echo "1. Uncomment the following code\n";
    echo "2. Replace the endpoint with your AG-UI server URL\n";
    echo "3. Add proper authentication headers\n\n";

    /*
    $observable = $agent->run($sseConfig);

    echo "Starting SSE stream...\n";
    
    $eventCount = 0;
    $subscription = $observable->subscribe(
        function (BaseEvent $event) use (&$eventCount) {
            $eventCount++;
            echo sprintf(
                "[%s] Event %d: %s (ID: %s)\n",
                date('Y-m-d H:i:s'),
                $eventCount,
                $event->getType(),
                $event->getId()
            );
            
            // Stop after 10 events for demo
            if ($eventCount >= 10) {
                return;
            }
        },
        function (Throwable $error) {
            echo "Error: " . $error->getMessage() . "\n";
        },
        function () {
            echo "Stream completed\n";
        }
    );

    // Run for 30 seconds
    echo "Listening for events (30 seconds)...\n";
    sleep(30);
    
    $agent->stop();
    echo "Agent stopped.\n";
    */

} catch (Exception $e) {
    echo "Error in SSE example: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: WebSocket Transport
echo "Example 2: WebSocket Transport\n";
echo "------------------------------\n";

try {
    $wsConfig = RunConfig::create('wss://api.example.com/ws', [
        'sessionId' => 'demo-session-' . uniqid(),
        'transportType' => 'websocket',
        'transportOptions' => [
            'protocols' => ['ag-ui'],
            'ping_interval' => 30
        ]
    ]);

    echo "Configuration:\n";
    echo "  Endpoint: " . $wsConfig->getEndpoint() . "\n";
    echo "  Transport: " . $wsConfig->getTransportType() . "\n";
    echo "  Protocols: " . json_encode($wsConfig->getTransportOption('protocols')) . "\n\n";

    echo "To run WebSocket example:\n";
    echo "1. Set up a WebSocket server that supports AG-UI protocol\n";
    echo "2. Update the endpoint URL\n";
    echo "3. Uncomment the connection code\n\n";

} catch (Exception $e) {
    echo "Error in WebSocket example: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: HTTP Binary Transport
echo "Example 3: HTTP Binary Transport\n";
echo "---------------------------------\n";

try {
    $binaryConfig = RunConfig::create('https://api.example.com/binary-stream', [
        'sessionId' => 'demo-session-' . uniqid(),
        'transportType' => 'http-binary',
        'transportOptions' => [
            'poll_interval' => 2000,
            'compression' => 'gzip'
        ]
    ]);

    echo "Configuration:\n";
    echo "  Endpoint: " . $binaryConfig->getEndpoint() . "\n";
    echo "  Transport: " . $binaryConfig->getTransportType() . "\n";
    echo "  Poll Interval: " . $binaryConfig->getTransportOption('poll_interval') . "ms\n";
    echo "  Compression: " . $binaryConfig->getTransportOption('compression') . "\n\n";

    echo "Binary transport is ideal for:\n";
    echo "- High-throughput scenarios\n";
    echo "- Protobuf-encoded messages\n";
    echo "- Efficient bandwidth usage\n\n";

} catch (Exception $e) {
    echo "Error in Binary example: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Transport Factory Features
echo "Example 4: Transport Factory Features\n";
echo "-------------------------------------\n";

echo "Supported transports: " . implode(', ', $transportFactory->getSupportedTypes()) . "\n";

$testUrls = [
    'wss://api.example.com/ws',
    'https://api.example.com/events',
    'https://api.example.com/binary',
    'https://api.example.com/api'
];

echo "\nRecommended transports for URLs:\n";
foreach ($testUrls as $url) {
    $recommended = $transportFactory->getRecommendedType($url);
    echo "  $url => $recommended\n";
}

echo "\nTransport factory supports:\n";
echo "- Automatic transport selection based on URL\n";
echo "- Fallback transport creation\n";
echo "- Custom transport registration\n";
echo "- Multiple transport instances\n\n";

// Example 5: Error Handling
echo "Example 5: Error Handling\n";
echo "-------------------------\n";

try {
    // Invalid URL
    $invalidConfig = RunConfig::create('invalid-url');
} catch (Exception $e) {
    echo "Caught validation error: " . $e->getMessage() . "\n";
}

try {
    // Unsupported transport
    $transportFactory->create('unknown-transport');
} catch (Exception $e) {
    echo "Caught transport error: " . $e->getMessage() . "\n";
}

echo "\nThe client handles various error scenarios:\n";
echo "- Invalid configuration\n";
echo "- Network connectivity issues\n";
echo "- Transport-specific errors\n";
echo "- Server disconnections\n\n";

echo "Example completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Set up a compatible AG-UI server\n";
echo "2. Configure authentication\n";
echo "3. Uncomment the streaming examples\n";
echo "4. Handle events based on your application needs\n";

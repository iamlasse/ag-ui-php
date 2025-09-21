# AG-UI LangGraph PHP Integration

PHP implementation of the AG-UI protocol for LangGraph, enabling seamless integration between LangGraph services and PHP applications.

## Features

- **Agent Implementation**: Complete AbstractAgent interface implementation for LangGraph
- **Event Translation**: Comprehensive translation between LangGraph events and AG-UI protocol events
- **Streaming Support**: Real-time event streaming using Server-Sent Events (SSE)
- **State Management**: Bidirectional state synchronization with snapshots and deltas
- **Tool Integration**: Full support for function calling and tool usage
- **Multiple Examples**: Simple client, streaming server, and reactive client implementations
- **Comprehensive Testing**: Full test suite covering all functionality

## Installation

### Requirements

- PHP 8.1 or higher
- Composer for dependency management

### Install via Composer

```bash
composer require ag-ui/langgraph-php
```

### Manual Installation

Clone the repository and install dependencies:

```bash
cd php-sdk/integrations/langgraph
composer install
```

## Quick Start

### Basic Usage

```php
<?php

require_once 'vendor/autoload.php';

use AGUI\Integrations\LangGraph\LangGraphAgent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;

// Create a simple tool
$weatherTool = new Tool();
$weatherTool->name = 'get_weather';
$weatherTool->description = 'Get the current weather for a location';
$weatherTool->parameters = (object) [
    'type' => 'object',
    'properties' => (object) [
        'location' => (object) [
            'type' => 'string',
            'description' => 'The city and state, e.g. San Francisco, CA'
        ]
    ],
    'required' => ['location']
];

// Create the LangGraph agent
$agent = new LangGraphAgent([
    'langGraphUrl' => 'http://localhost:8000',
    'apiKey' => 'your-api-key-here',
    'assistantId' => 'weather-assistant',
    'threadId' => 'thread-' . uniqid(),
    'description' => 'Weather assistant',
    'debug' => true,
]);

// Create initial messages
$messages = [
    new Message([
        'id' => 'msg-1',
        'role' => 'user',
        'content' => 'What\'s the weather like in San Francisco, CA?',
    ]),
];

// Define available tools
$tools = [$weatherTool];

// Add event subscribers
$agent->subscribe([
    'onRunStarted' => function ($params) {
        echo "=== RUN STARTED ===\n";
        echo "Thread ID: {$params->threadId}\n";
        echo "Run ID: {$params->runId}\n";
        echo "\n";
    },

    'onNewMessage' => function ($params) {
        $message = $params->message;
        echo "[MESSAGE] {$message->role}: {$message->content}\n";
    },

    'onNewToolCall' => function ($params) {
        $toolCall = $params->toolCall;
        echo "[TOOL_CALL] {$toolCall->function->name}(" . json_encode($toolCall->function->arguments) . ")\n";
    },

    'onRunFinished' => function ($params) {
        echo "=== RUN FINISHED ===\n";
        echo "\n";
    },
]);

// Run the agent
try {
    $result = $agent->runAgent([
        'tools' => $tools,
        'context' => [],
        'forwardedProps' => [
            'nodeName' => 'php-client',
            'clientType' => 'php',
        ]
    ]);

    echo "New messages count: " . count($result->newMessages) . "\n";

} catch (Exception $e) {
    echo "Error running agent: " . $e->getMessage() . "\n";
}
```

### Reactive Client Example

```php
<?php

require_once 'vendor/autoload.php';

use AGUI\Integrations\LangGraph\LangGraphAgent;

// Create reactive client
$client = new ReactiveLangGraphClient([
    'langGraphUrl' => 'http://localhost:8000',
    'apiKey' => 'your-api-key-here',
    'debug' => true,
]);

// Send message and get response
$result = $client->sendMessage("What's the weather like in Tokyo?");

if (!$result['success']) {
    echo "Error: {$result['error']}\n";
} else {
    echo "Response received successfully!\n";
}
```

## Components

### LangGraphAgent

The main agent class that implements the AbstractAgent interface:

```php
class LangGraphAgent extends AbstractAgent
{
    public function __construct(array $config = [])
    public function runAgent(array $input): RunAgentResult
    public function setLangGraphUrl(string $url): self
    public function setApiKey(string $apiKey): self
    // ... additional methods
}
```

### EventTranslator

Handles translation between LangGraph events and AG-UI protocol events:

```php
class EventTranslator
{
    public function translate(array $langGraphEvent, string $threadId, string $runId): ?BaseEvent
    public function clearActiveEvents(): void
    public function getActiveTextMessages(): array
    public function getActiveToolCalls(): array
}
```

## Configuration

### Agent Configuration

```php
$agent = new LangGraphAgent([
    'langGraphUrl' => 'http://localhost:8000',     // LangGraph service URL
    'apiKey' => 'your-api-key',                    // API key for authentication
    'assistantId' => 'my-assistant',               // Assistant identifier
    'threadId' => 'thread-123',                    // Thread identifier
    'description' => 'My assistant',               // Agent description
    'debug' => false,                             // Enable debug mode
    'langGraphConfig' => [                         // Additional LangGraph config
        'recursion_limit' => 25,
        'checkpoint_ns' => 'my_checkpoint',
    ],
]);
```

### Supported Event Types

The integration supports all major LangGraph event types:

- **Run Events**: `run/start`, `run/end`, `run/error`
- **Message Events**: `messages/partial`, `messages/complete`
- **Tool Events**: `tool_calls/start`, `tool_calls/end`
- **State Events**: `state/update` (snapshot, delta, messages)
- **Step Events**: `step/start`, `step/end`
- **Thinking Events**: `thinking/start`, `thinking/end`
- **Custom Events**: Any custom event types

## Examples

### 1. Simple Client
Basic client demonstrating agent setup and event handling.

```bash
cd examples
php simple-client.php
```

### 2. Streaming Server
HTTP server with ReactPHP supporting both non-streaming and streaming endpoints.

```bash
cd examples
php streaming-server.php
```

### 3. Reactive Client
Advanced client with reactive event handling and interactive conversation.

```bash
cd examples
php reactive-client.php
```

## Testing

Run the test suite:

```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run specific test file
./vendor/bin/phpunit tests/LangGraphAgentTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage tests/
```

## API Reference

### LangGraphAgent Methods

| Method | Description |
|--------|-------------|
| `__construct(array $config)` | Create agent with configuration |
| `runAgent(array $input)` | Execute the agent with given input |
| `subscribe(array $handlers)` | Subscribe to events |
| `addMessage(Message $message)` | Add message to conversation |
| `setMessages(array $messages)` | Set conversation messages |
| `getLangGraphUrl()` | Get configured LangGraph URL |
| `setLangGraphUrl(string $url)` | Set LangGraph URL |
| `getApiKey()` | Get API key |
| `setApiKey(string $key)` | Set API key |

### Event Types

#### Run Events
- `RunStartedEvent`: Emitted when agent run starts
- `RunFinishedEvent`: Emitted when agent run completes successfully
- `RunErrorEvent`: Emitted when agent run encounters an error

#### Message Events
- `TextMessageStartEvent`: Emitted when text message starts
- `TextMessageContentEvent`: Emitted for content deltas
- `TextMessageEndEvent`: Emitted when text message completes

#### Tool Events
- `ToolCallStartEvent`: Emitted when tool call starts
- `ToolCallEndEvent`: Emitted when tool call completes

#### State Events
- `StateSnapshotEvent`: Complete state snapshot
- `StateDeltaEvent`: Incremental state changes
- `MessagesSnapshotEvent`: Conversation history snapshot

#### Step Events
- `StepStartedEvent`: Emitted when execution step starts
- `StepFinishedEvent`: Emitted when execution step completes

## Error Handling

The integration provides comprehensive error handling:

```php
try {
    $result = $agent->runAgent($input);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    // HTTP request errors
    echo "HTTP Error: " . $e->getMessage();
} catch (\RuntimeException $e) {
    // Runtime errors
    echo "Runtime Error: " . $e->getMessage();
} catch (\Exception $e) {
    // General errors
    echo "Error: " . $e->getMessage();
}
```

## Best Practices

### 1. State Management
- Use thread IDs to maintain conversation state
- Clear active events between runs
- Implement proper error recovery

### 2. Event Handling
- Subscribe to all relevant events
- Handle errors gracefully
- Implement proper cleanup

### 3. Performance
- Reuse agent instances when possible
- Implement proper connection pooling
- Use streaming for long-running operations

### 4. Security
- Use HTTPS in production
- Validate API keys and tokens
- Implement proper authentication

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:

- Create an issue on GitHub
- Check the documentation
- Join the community discussions

## Changelog

### v1.0.0
- Initial release
- Complete LangGraph integration
- Event translation system
- Multiple example implementations
- Comprehensive test suite
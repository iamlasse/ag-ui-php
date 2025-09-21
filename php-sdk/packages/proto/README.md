# AG-UI PHP Protocol Buffers

This package provides PHP Protocol Buffer support for AG-UI events, maintaining API compatibility with the TypeScript implementation.

## 📦 Installation

```bash
composer require ag-ui/proto
```

## 🚀 Quick Start

```php
<?php

use AGUI\Proto\Proto;
use AGUI\Proto\EventTypes;

// Encode an event
$event = [
    'type' => EventTypes::TEXT_MESSAGE_START,
    'timestamp' => time() * 1000,
    'messageId' => 'msg-123',
    'role' => 'assistant'
];

$encoded = Proto::encode($event);

// Decode the event
$decoded = Proto::decode($encoded);
echo $decoded['type']; // TEXT_MESSAGE_START
```

## 📋 Features

- **🔄 Full API Compatibility** - Maintains compatibility with TypeScript implementation
- **📝 Event Types** - All AG-UI event types supported
- **🛠 Code Generation** - Generate PHP classes from .proto files
- **✅ Type Safety** - Strong typing with PHP 8.1+
- **📊 JSON Patch** - Support for JSON Patch operations in state deltas
- **🧪 Tested** - Comprehensive test coverage

## 📖 Event Types

### Text Message Events
```php
// Text message start
$event = [
    'type' => EventTypes::TEXT_MESSAGE_START,
    'messageId' => 'msg-123',
    'role' => 'assistant'
];

// Text message content
$event = [
    'type' => EventTypes::TEXT_MESSAGE_CONTENT,
    'messageId' => 'msg-123',
    'delta' => 'Hello, world!'
];

// Text message end
$event = [
    'type' => EventTypes::TEXT_MESSAGE_END,
    'messageId' => 'msg-123'
];
```

### Tool Call Events
```php
// Tool call start
$event = [
    'type' => EventTypes::TOOL_CALL_START,
    'toolCallId' => 'call-123',
    'toolCallName' => 'search',
    'parentMessageId' => 'msg-456'
];

// Tool call arguments
$event = [
    'type' => EventTypes::TOOL_CALL_ARGS,
    'toolCallId' => 'call-123',
    'delta' => '{"query": "test"}'
];

// Tool call end
$event = [
    'type' => EventTypes::TOOL_CALL_END,
    'toolCallId' => 'call-123'
];
```

### State Events
```php
// State snapshot
$event = [
    'type' => EventTypes::STATE_SNAPSHOT,
    'snapshot' => [
        'currentStep' => 1,
        'progress' => 0.5,
        'data' => ['key' => 'value']
    ]
];

// State delta with JSON Patch operations
use AGUI\Proto\JsonPatchOperations;

$event = [
    'type' => EventTypes::STATE_DELTA,
    'delta' => [
        [
            'op' => JsonPatchOperations::ADD,
            'path' => '/items/0',
            'value' => 'new item'
        ],
        [
            'op' => JsonPatchOperations::REPLACE,
            'path' => '/progress',
            'value' => 0.75
        ]
    ]
];
```

### Messages Snapshot
```php
$event = [
    'type' => EventTypes::MESSAGES_SNAPSHOT,
    'messages' => [
        [
            'id' => 'msg-1',
            'role' => 'user',
            'content' => 'Hello',
            'toolCalls' => []
        ],
        [
            'id' => 'msg-2',
            'role' => 'assistant',
            'content' => 'Hi there!',
            'toolCalls' => [
                [
                    'id' => 'call-1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"query": "test"}'
                    ]
                ]
            ]
        ]
    ]
];
```

### Run Events
```php
// Run started
$event = [
    'type' => EventTypes::RUN_STARTED,
    'threadId' => 'thread-123',
    'runId' => 'run-456'
];

// Run finished
$event = [
    'type' => EventTypes::RUN_FINISHED,
    'threadId' => 'thread-123',
    'runId' => 'run-456',
    'result' => ['status' => 'success', 'output' => 'Done!']
];

// Run error
$event = [
    'type' => EventTypes::RUN_ERROR,
    'code' => 'TIMEOUT',
    'message' => 'Operation timed out'
];
```

### Step Events
```php
// Step started
$event = [
    'type' => EventTypes::STEP_STARTED,
    'stepName' => 'data-processing'
];

// Step finished
$event = [
    'type' => EventTypes::STEP_FINISHED,
    'stepName' => 'data-processing'
];
```

### Custom Events
```php
$event = [
    'type' => EventTypes::CUSTOM,
    'name' => 'my-custom-event',
    'value' => [
        'custom' => 'data',
        'priority' => 'high'
    ]
];
```

### Raw Events
```php
$event = [
    'type' => EventTypes::RAW,
    'event' => [
        'originalType' => 'system.notification',
        'data' => ['message' => 'System updated']
    ],
    'source' => 'external-system'
];
```

## 🛠 Development

### Generate Protocol Buffer Classes

```bash
# Generate PHP classes from .proto files
composer run generate

# Alternative command
composer run proto:generate
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage
```

### Code Quality

```bash
# Run linting
composer lint

# Fix linting issues
composer lint-fix

# Run static analysis
composer analyze
```

## 🏗 Generated Classes Structure

The generated PHP classes are organized as follows:

```
src/Generated/
├── Ag_ui/                    # Main event classes
│   ├── Event.php            # Main event wrapper
│   ├── EventType.php        # Event type enum
│   ├── BaseEvent.php        # Base event class
│   ├── TextMessageStartEvent.php
│   ├── TextMessageContentEvent.php
│   ├── ToolCallStartEvent.php
│   ├── StateSnapshotEvent.php
│   ├── StateDeltaEvent.php
│   └── ...
└── GPBMetadata/             # Protocol buffer metadata
    ├── Events.php
    ├── Patch.php
    └── Types.php
```

## 🔧 Protocol Buffer Files

The package includes these .proto files:

- **`events.proto`** - Main event definitions and types
- **`patch.proto`** - JSON Patch operation definitions  
- **`types.proto`** - Common type definitions (Message, ToolCall)

## ⚙️ Configuration

### Media Type

The package uses the standard AG-UI media type:

```php
echo Proto::AGUI_MEDIA_TYPE; // application/vnd.ag-ui.event+proto
```

### Error Handling

The encoder throws exceptions for invalid data:

```php
try {
    $encoded = Proto::encode($event);
} catch (InvalidArgumentException $e) {
    // Invalid event type or missing required fields
    echo "Invalid event: " . $e->getMessage();
} catch (RuntimeException $e) {
    // Encoding/decoding failure
    echo "Encoding failed: " . $e->getMessage();
}
```

## 🧪 Testing

The package includes comprehensive tests covering:

- All event types
- Encoding/decoding round trips
- Error conditions
- API compatibility with TypeScript implementation
- JSON Patch operations

Run the tests:

```bash
composer test
```

## 📚 API Reference

### Proto

Main facade class providing encode/decode functionality.

#### Methods

- `Proto::encode(array $event): string` - Encode event to protobuf binary
- `Proto::decode(string $data): array` - Decode protobuf binary to event array

### EventTypes

Constants for all supported event types.

#### Constants

- `EventTypes::TEXT_MESSAGE_START`
- `EventTypes::TEXT_MESSAGE_CONTENT` 
- `EventTypes::TEXT_MESSAGE_END`
- `EventTypes::TOOL_CALL_START`
- `EventTypes::TOOL_CALL_ARGS`
- `EventTypes::TOOL_CALL_END`
- `EventTypes::STATE_SNAPSHOT`
- `EventTypes::STATE_DELTA`
- `EventTypes::MESSAGES_SNAPSHOT`
- `EventTypes::RAW`
- `EventTypes::CUSTOM`
- `EventTypes::RUN_STARTED`
- `EventTypes::RUN_FINISHED`
- `EventTypes::RUN_ERROR`
- `EventTypes::STEP_STARTED`
- `EventTypes::STEP_FINISHED`

#### Methods

- `EventTypes::all(): array` - Get all event types
- `EventTypes::isValid(string $type): bool` - Check if event type is valid

### JsonPatchOperations

Constants for JSON Patch operations.

#### Constants

- `JsonPatchOperations::ADD`
- `JsonPatchOperations::REMOVE`
- `JsonPatchOperations::REPLACE`
- `JsonPatchOperations::MOVE`
- `JsonPatchOperations::COPY`
- `JsonPatchOperations::TEST`

#### Methods

- `JsonPatchOperations::all(): array` - Get all operations
- `JsonPatchOperations::isValid(string $op): bool` - Check if operation is valid

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make changes
4. Add tests
5. Run quality checks: `composer lint && composer analyze && composer test`
6. Submit a pull request

## 📄 License

This package is licensed under the MIT License.

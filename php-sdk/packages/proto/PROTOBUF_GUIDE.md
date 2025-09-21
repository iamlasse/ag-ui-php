# AG-UI PHP Protocol Buffers Implementation Guide

## Overview

This document describes the PHP implementation of AG-UI Protocol Buffers, which maintains full API compatibility with the TypeScript version while providing efficient binary serialization for event data.

## Architecture

### Generated Classes

The protobuf compiler generates PHP classes in the `src/Generated/` directory:

```
src/Generated/
├── Ag_ui/                        # Event classes (from ag_ui package)
│   ├── Event.php                # Main event wrapper (oneof)
│   ├── EventType.php            # Event type enumeration
│   ├── BaseEvent.php            # Base event with common fields
│   ├── JsonPatchOperation.php   # JSON patch operation
│   ├── JsonPatchOperationType.php # JSON patch operation types
│   ├── Message.php              # Message structure
│   ├── ToolCall.php             # Tool call structure
│   └── [EventName]Event.php     # Specific event classes
└── GPBMetadata/                 # Metadata classes
    ├── Events.php               # Events metadata
    ├── Patch.php                # Patch metadata
    └── Types.php                # Types metadata
```

### Wrapper Classes

The package provides high-level wrapper classes for easier usage:

- **`ProtoEncoder`** - Core encoding/decoding logic
- **`Proto`** - Static facade for encoding/decoding
- **`EventTypes`** - Constants for event types
- **`JsonPatchOperations`** - Constants for JSON patch operations

## Protocol Buffer Definitions

### events.proto

Defines the main event types and structures:

```protobuf
syntax = "proto3";
package ag_ui;

import "google/protobuf/struct.proto";
import "patch.proto";
import "types.proto";

enum EventType {
  TEXT_MESSAGE_START = 0;
  TEXT_MESSAGE_CONTENT = 1;
  // ... other event types
}

message Event {
  oneof event {
    TextMessageStartEvent text_message_start = 1;
    TextMessageContentEvent text_message_content = 2;
    // ... other event types
  }
}
```

### patch.proto

Defines JSON Patch operations for state deltas:

```protobuf
syntax = "proto3";
package ag_ui;

enum JsonPatchOperationType {
  ADD = 0;
  REMOVE = 1;
  REPLACE = 2;
  MOVE = 3;
  COPY = 4;
  TEST = 5;
}

message JsonPatchOperation {
  JsonPatchOperationType op = 1;
  string path = 2;
  optional string from = 3;
  optional google.protobuf.Value value = 4;
}
```

### types.proto

Defines common types used across events:

```protobuf
syntax = "proto3";
package ag_ui;

message ToolCall {
  string id = 1;
  string type = 2;
  Function function = 3;
  
  message Function {
    string name = 1;
    string arguments = 2;
  }
}

message Message {
  string id = 1;
  string role = 2;
  optional string content = 3;
  repeated ToolCall tool_calls = 5;
  // ... other fields
}
```

## Encoding Process

### 1. Event Validation

The encoder first validates the input event:

```php
if (!isset($event['type'])) {
    throw new InvalidArgumentException('Event must have a type field');
}
```

### 2. Field Name Conversion

Converts between PHP conventions and protobuf field names:

- PHP: `messageId` → Protobuf: `message_id`
- PHP: `toolCallName` → Protobuf: `tool_call_name`

### 3. Event Type Mapping

Maps string event types to protobuf enum values:

```php
$eventType = $this->getEventTypeConstant($event['type']);
// 'TEXT_MESSAGE_START' → Ag_ui\EventType::TEXT_MESSAGE_START (0)
```

### 4. Data Transformation

Applies event-specific transformations:

- **Messages Snapshot**: Ensures `toolCalls` array is always present
- **State Delta**: Converts JSON patch operation strings to enum values
- **Value Types**: Converts PHP values to `google.protobuf.Value`

### 5. Protobuf Message Creation

Creates the specific event message and wraps it in the main Event:

```php
$eventMessage = new $eventClassName([
    'base_event' => $baseEventData,
    ...$eventData
]);

$mainEvent = new Event([
    $oneofField => $eventMessage
]);
```

## Decoding Process

### 1. Protobuf Deserialization

Deserializes binary data to protobuf message:

```php
$event = new Event();
$event->mergeFromString($data);
```

### 2. Oneof Field Detection

Uses reflection to find the populated oneof field:

```php
foreach ($methods as $method) {
    if (strpos($method->getName(), 'get') === 0) {
        $value = $event->$getter();
        if ($value !== null) {
            $eventMessage = $value;
            break;
        }
    }
}
```

### 3. Data Extraction

Extracts event-specific data using reflection:

```php
$data = [];
foreach ($methods as $method) {
    if (strpos($methodName, 'get') === 0) {
        $fieldName = $this->fromCamelCase(substr($methodName, 3));
        $value = $eventMessage->$methodName();
        $data[strtolower($fieldName)] = $value;
    }
}
```

### 4. Post-processing

Applies reverse transformations:

- Converts enum values back to strings
- Filters out null values
- Handles special cases (empty arrays, etc.)

## API Compatibility

### TypeScript vs PHP API

The PHP implementation maintains API compatibility with TypeScript:

**TypeScript:**
```typescript
import { encode, decode, AGUI_MEDIA_TYPE } from '@ag-ui/proto';

const event = {
  type: 'TEXT_MESSAGE_START',
  messageId: 'msg-123',
  role: 'assistant'
};

const encoded = encode(event);
const decoded = decode(encoded);
```

**PHP:**
```php
use AGUI\Proto\Proto;
use AGUI\Proto\EventTypes;

$event = [
    'type' => EventTypes::TEXT_MESSAGE_START,
    'messageId' => 'msg-123',
    'role' => 'assistant'
];

$encoded = Proto::encode($event);
$decoded = Proto::decode($encoded);
```

### Differences and Considerations

1. **Field Names**: Decoded field names in PHP use lowercase with underscores
2. **Constants**: PHP uses class constants instead of string literals
3. **Arrays**: PHP uses associative arrays instead of objects
4. **Null Handling**: PHP filters out null values more aggressively

## Performance Considerations

### Binary Size

Protocol Buffers provide efficient binary serialization:

- Text events: ~50-200 bytes
- Complex events with tool calls: ~200-800 bytes
- Messages snapshot: Size depends on message count and content

### Memory Usage

The PHP implementation uses:

- Generated classes for type safety
- Reflection for dynamic field extraction
- Lazy loading of protobuf metadata

### Optimization Tips

1. **Reuse Encoder**: Create one `ProtoEncoder` instance and reuse it
2. **Batch Processing**: Process multiple events together when possible
3. **Field Filtering**: Remove unnecessary fields before encoding
4. **Memory Management**: Unset large arrays after encoding/decoding

## Error Handling

### Common Exceptions

- **`InvalidArgumentException`**: Invalid event type or missing required fields
- **`RuntimeException`**: Protobuf encoding/decoding errors

### Error Recovery

```php
try {
    $encoded = Proto::encode($event);
} catch (InvalidArgumentException $e) {
    // Log invalid event format
    error_log("Invalid event: " . $e->getMessage());
    // Provide fallback or default event
} catch (RuntimeException $e) {
    // Log encoding failure
    error_log("Encoding failed: " . $e->getMessage());
    // Retry or skip this event
}
```

## Testing Strategy

### Unit Tests

- **Round-trip Testing**: Encode → Decode → Verify
- **Edge Cases**: Null values, empty arrays, special characters
- **Error Conditions**: Invalid types, malformed data
- **Compatibility**: Verify output matches TypeScript behavior

### Integration Tests

- **Cross-platform**: Verify PHP-encoded data can be decoded by TypeScript
- **Performance**: Benchmark encoding/decoding speed
- **Memory**: Monitor memory usage with large datasets

## Deployment

### Dependencies

- **PHP**: 8.1+
- **google/protobuf**: ^3.25.0
- **symfony/serializer**: ^7.0 (optional, for additional serialization)

### Installation

```bash
composer require ag-ui/proto
```

### Code Generation

```bash
# Generate PHP classes from .proto files
composer run generate

# Or use the script directly
bash scripts/generate-proto.sh
```

## Troubleshooting

### Common Issues

1. **Class Not Found**: Run `composer dump-autoload` after generation
2. **Protoc Not Found**: Install Protocol Buffers compiler
3. **Memory Errors**: Increase PHP memory limit for large events
4. **Encoding Errors**: Check event structure matches expected format

### Debug Mode

Enable debug output in the encoder:

```php
$encoder = new ProtoEncoder();
// Add debug logging in ProtoEncoder if needed
```

## Future Enhancements

### Planned Features

1. **Streaming Support**: Handle large event streams efficiently
2. **Compression**: Optional compression for large payloads
3. **Schema Validation**: Runtime validation against .proto schemas
4. **Performance Optimization**: Further optimize encoding/decoding speed

### Compatibility

The implementation will maintain backward compatibility with:

- Existing .proto file definitions
- TypeScript API signatures
- Binary format compatibility across versions

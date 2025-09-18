# TypeScript to PHP Migration Guide

This guide provides detailed instructions for migrating TypeScript dependencies to their PHP equivalents in the AG-UI project.

## Overview

The AG-UI TypeScript SDK uses several libraries that have mature PHP equivalents. This guide maps each TypeScript dependency to its PHP counterpart and provides migration examples.

## Dependency Mapping

| TypeScript | PHP Equivalent | Migration Difficulty | Notes |
|------------|----------------|-------------------|-------|
| rxjs (v7.8.1) | react/promise | Medium | Different paradigm, simpler in PHP |
| zod (v3.22.4) | respect/validation | Easy | Similar fluent API |
| uuid (v11.1.0) | ramsey/uuid | Easy | Direct equivalent |
| fast-json-patch (v3.1.1) | swaggest/json-diff | Easy | RFC 6902 compliant |
| @bufbuild/protobuf (v2.2.5) | google/protobuf | Medium | Code generation required |

## 1. Reactive Programming: rxjs → react/promise

### TypeScript (rxjs)
```typescript
import { Observable, of } from 'rxjs';
import { map, switchMap, catchError } from 'rxjs/operators';

function getEvents(): Observable<BaseEvent> {
  return of('data').pipe(
    map(data => ({ type: 'TEXT_MESSAGE', data })),
    switchMap(event => processEvent(event)),
    catchError(error => of({ type: 'ERROR', error: error.message }))
  );
}

// Subscription
getEvents().subscribe({
  next: event => console.log('Event:', event),
  error: err => console.error('Error:', err),
  complete: () => console.log('Complete')
});
```

### PHP (react/promise)
```php
use React\Promise\Promise;
use React\Promise\PromiseInterface;

function getEvents(): PromiseInterface
{
    return new Promise(function ($resolve, $reject) {
        try {
            $data = 'data';
            $event = ['type' => 'TEXT_MESSAGE', 'data' => $data];

            processEvent($event)
                ->then($resolve)
                ->otherwise($reject);
        } catch (Throwable $error) {
            $resolve(['type' => 'ERROR', 'error' => $error->getMessage()]);
        }
    });
}

// Consumption
getEvents()
    ->then(function ($event) {
        echo 'Event: ' . json_encode($event) . "\n";
    })
    ->otherwise(function ($error) {
        echo 'Error: ' . $error->getMessage() . "\n";
    });
```

### Migration Strategy:
1. Replace Observable with Promise
2. Use promise chaining instead of operators
3. Handle errors with `otherwise()` instead of `catchError`
4. Consider generators for streaming scenarios

## 2. Schema Validation: zod → respect/validation

### TypeScript (zod)
```typescript
import { z } from 'zod';

const RunAgentInputSchema = z.object({
  input: z.string().min(1),
  agentId: z.string().uuid().optional(),
  timeout: z.number().min(1000).optional(),
  config: z.record(z.unknown()).optional()
});

type RunAgentInput = z.infer<typeof RunAgentInputSchema>;

function validateInput(input: unknown): RunAgentInput {
  return RunAgentInputSchema.parse(input);
}
```

### PHP (respect/validation)
```php
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class RunAgentInputValidator
{
    public static function validate(array $input): array
    {
        $validator = v::key('input', v::stringType()->length(1, null))
                      ->key('agentId', v::optional(v::uuid()))
                      ->key('timeout', v::optional(v::intType()->min(1000)))
                      ->key('config', v::optional(v::arrayType()));

        try {
            $validator->assert($input);
            return $input;
        } catch (NestedValidationException $e) {
            throw new InvalidArgumentException('Invalid input: ' . $e->getFullMessage());
        }
    }
}

// Usage
$input = ['input' => 'Hello', 'agentId' => '550e8400-e29b-41d4-a716-446655440000'];
$validated = RunAgentInputValidator::validate($input);
```

### Migration Strategy:
1. Create validator classes for each schema
2. Use `assert()` for strict validation or `validate()` for boolean result
3. Handle NestedValidationException for detailed error messages
4. Chain validators using `->` operator

## 3. UUID Generation: uuid → ramsey/uuid

### TypeScript (uuid)
```typescript
import { v4 as uuidv4, v5 as uuidv5 } from 'uuid';

const eventId = uuidv4();
const sessionId = uuidv5('user123', NAMESPACE_DNS);
const isValid = uuid.validate(eventId);
```

### PHP (ramsey/uuid)
```php
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

$eventId = Uuid::uuid4();
$sessionId = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'user123');
$isValid = Uuid::isValid($eventId->toString());

// Get string representation
$eventIdString = $eventId->toString();
$eventIdBytes = $eventId->getBytes();
```

### Migration Strategy:
1. Direct mapping of UUID generation methods
2. Use `toString()` for string representation
3. Use `getBytes()` for binary representation
4. `isValid()` checks string UUID validity

## 4. JSON Patch: fast-json-patch → swaggest/json-diff

### TypeScript (fast-json-patch)
```typescript
import * as jsonpatch from 'fast-json-patch';

const original = { name: 'John', age: 30 };
const modified = { name: 'John Doe', age: 31 };

const patch = jsonpatch.compare(original, modified);
const result = jsonpatch.applyPatch(original, patch);
```

### PHP (swaggest/json-diff)
```php
use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPatch;

$original = ['name' => 'John', 'age' => 30];
$modified = ['name' => 'John Doe', 'age' => 31];

$diff = new JsonDiff($original, $modified);
$patch = $diff->getPatch();

$jsonPatch = new JsonPatch();
$jsonPatch->import(json_encode($patch));

$result = $original;
$jsonPatch->apply($result);
```

### Migration Strategy:
1. Use JsonDiff for creating patches
2. Use JsonPatch for applying patches
3. Handle JSON encoding/decoding
4. Use RFC 6902 compliant operations

## 5. Protocol Buffers: @bufbuild/protobuf → google/protobuf

### TypeScript (@bufbuild/protobuf)
```typescript
import { BaseEvent } from '@bufbuild/protobuf';

const event = new BaseEvent({
  type: 'TEXT_MESSAGE',
  data: new TextMessage({
    content: 'Hello, world!'
  })
});

const binary = event.toBinary();
const restored = BaseEvent.fromBinary(binary);
```

### PHP (google/protobuf)
```php
// First, generate PHP classes from .proto files
// protoc --php_out=. path/to/your.proto

use Your\Protobuf\BaseEvent;
use Your\Protobuf\TextMessage;

$event = new BaseEvent();
$event->setType('TEXT_MESSAGE');

$textMessage = new TextMessage();
$textMessage->setContent('Hello, world!');
$event->setData($textMessage);

$binary = $event->serializeToString();
$restored = new BaseEvent();
$restored->mergeFromString($binary);
```

### Migration Strategy:
1. Set up Protocol Buffers code generation
2. Use `protoc` with PHP output plugin
3. Learn generated class methods
4. Handle binary serialization/deserialization

## Code Generation Setup

### 1. Install Protocol Buffers Compiler
```bash
# macOS
brew install protobuf

# Ubuntu
sudo apt-get install protobuf-compiler

# Verify installation
protoc --version
```

### 2. Install PHP Protobuf Plugin
```bash
# Add to composer.json
{
    "require-dev": {
        "google/protobuf": "^3.25.0"
    }
}
```

### 3. Create build script
```bash
#!/bin/bash
# build-protobuf.sh

protoc --php_out=src/Generated \
       --plugin=protoc-gen-php=./vendor/bin/protoc-gen-php \
       protos/*.proto
```

## Performance Considerations

### 1. Memory Usage
- **React/Promise**: Lightweight, minimal memory overhead
- **Respect/Validation**: Efficient validation with early termination
- **Ramsey/UUID**: Consider `ext-uuid` for high-performance scenarios
- **Swaggest/Json-Diff**: Efficient diff algorithms
- **Google/Protobuf**: Use `ext-protobuf` for better performance

### 2. CPU Performance
```bash
# Install optional extensions
pecl install uuid
pecl install protobuf
```

### 3. Bundle Size
PHP packages have minimal overhead compared to JavaScript:
- No tree-shaking needed
- Composer handles dependency optimization
- OPCache for bytecode caching

## Testing Strategies

### 1. Unit Testing
```php
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testValidInputPasses()
    {
        $input = ['input' => 'Hello'];
        $result = RunAgentInputValidator::validate($input);

        $this->assertEquals($input, $result);
    }

    public function testInvalidInputThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        $input = ['input' => '']; // Invalid: empty string
        RunAgentInputValidator::validate($input);
    }
}
```

### 2. Integration Testing
```php
class EventStreamTest extends TestCase
{
    public function testEventStreamProcessesEvents()
    {
        $stream = new AGUIEventStream();
        $results = [];

        $stream->getEventEmitter()->on('run_finished', function ($data) use (&$results) {
            $results[] = $data;
        });

        $promise = $stream->runAgent(['input' => 'test']);

        // Wait for promise to resolve
        $promise->then(function () use ($results) {
            $this->assertCount(1, $results);
        });
    }
}
```

## Error Handling Patterns

### 1. Promise Error Handling
```php
$stream->runAgent($input)
    ->then(function ($result) {
        // Success
        return $result;
    })
    ->otherwise(function ($error) {
        // Handle specific error types
        if ($error instanceof ValidationException) {
            return ['error' => 'Validation failed'];
        }

        if ($error instanceof TimeoutException) {
            return ['error' => 'Operation timed out'];
        }

        // Generic error
        return ['error' => $error->getMessage()];
    });
```

### 2. Validation Error Handling
```php
try {
    $validated = EventValidator::validateEvent($event);
} catch (NestedValidationException $e) {
    $errors = [];
    foreach ($e->getMessages() as $field => $message) {
        $errors[$field] = $message;
    }

    throw new ValidationException('Invalid event data', 0, $e);
}
```

## Configuration Management

### 1. Environment Variables
```php
class Config
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'protobuf_path' => getenv('PROTOBUF_PATH') ?? 'src/Generated',
            'uuid_version' => getenv('UUID_VERSION') ?? 4,
            'validation_strict' => getenv('VALIDATION_STRICT') ?? true
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
```

### 2. Service Container Setup
```php
use Psr\Container\ContainerInterface;

class AGUIContainer implements ContainerInterface
{
    private array $services = [];

    public function get(string $id)
    {
        if (!isset($this->services[$id])) {
            $this->services[$id] = $this->create($id);
        }

        return $this->services[$id];
    }

    private function create(string $id): object
    {
        switch ($id) {
            case EventEmitter::class:
                return new EventEmitter();
            case EventValidator::class:
                return new EventValidator();
            case StateManager::class:
                return new StateManager();
            default:
                throw new InvalidArgumentException("Unknown service: $id");
        }
    }
}
```

## Deployment Considerations

### 1. Production Build
```bash
#!/bin/bash
# deploy.sh

# Install dependencies without dev
composer install --no-dev --optimize-autoloader --no-interaction

# Generate protobuf classes
./build-protobuf.sh

# Clear caches
php artisan cache:clear
php artisan config:clear

# Set permissions
chmod -R 755 storage/
chmod -R 644 bootstrap/cache/
```

### 2. Docker Configuration
```dockerfile
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libprotobuf-dev \
    libuuid-dev

# Install PHP extensions
RUN pecl install uuid protobuf \
    && docker-php-ext-enable uuid protobuf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html
WORKDIR /var/www/html

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Generate protobuf classes
RUN protoc --php_out=src/Generated protos/*.proto

EXPOSE 9000
CMD ["php-fpm"]
```

## Conclusion

The migration from TypeScript to PHP dependencies is straightforward with the identified packages. Key takeaways:

1. **React/Promise** provides a solid foundation for async operations
2. **Respect/Validation** offers a Zod-like validation experience
3. **Ramsey/UUID** is the de facto standard for UUID handling in PHP
4. **Swaggest/Json-Diff** provides complete JSON Patch support
5. **Google/Protobuf** is the official implementation with good performance

The migration should be done incrementally, starting with core functionality and gradually adding advanced features.
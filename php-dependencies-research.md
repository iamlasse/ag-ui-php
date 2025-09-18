# PHP Equivalents Research Report

This report provides comprehensive analysis of PHP equivalents for TypeScript dependencies used in the AG-UI project.

## Executive Summary

The AG-UI TypeScript SDK uses several key libraries that have mature PHP equivalents. This research identifies production-ready PHP packages that provide similar functionality with PHP 8.1+ compatibility.

## 1. Reactive Programming: rxjs (v7.8.1) → PHP Alternatives

### Primary Recommendation: React/Promise with Extensions
**Package:** `react/promise`
**Latest Version:** ^3.2.0
**PHP Requirements:** PHP 7.1+ (PHP 8.1+ recommended)
**Composer Support:** Yes

```bash
composer require react/promise
```

**Basic Usage:**
```php
use React\Promise\Promise;
use React\Promise\Deferred;

// Creating a promise
$promise = new Promise(function ($resolve, $reject) {
    // Async operation
    $resolve('Result');
});

// Chaining operations
$promise
    ->then(function ($result) {
        return strtoupper($result);
    })
    ->then(function ($result) {
        echo $result; // "RESULT"
    });

// Using Deferred for more control
$deferred = new Deferred();
$promise = $deferred->promise();

// Resolve later
$deferred->resolve('Async result');
```

**Alternative: RxPHP (Limited Maintenance)**
**Package:** `reactivex/rxphp`
**Status:** Limited maintenance, not recommended for new projects
**PHP Requirements:** PHP 7.1+

### Why React/Promise over RxPHP?
- **Active Maintenance**: React/Promise is actively maintained
- **Community Adoption**: Widely used in PHP async ecosystem
- **Performance**: Lightweight and efficient
- **Compatibility**: Works well with modern PHP async frameworks

## 2. Schema Validation: zod (v3.22.4) → PHP Alternatives

### Primary Recommendation: Respect/Validation
**Package:** `respect/validation`
**Latest Version:** ^2.3.0
**PHP Requirements:** PHP 8.0+
**Composer Support:** Yes

```bash
composer require respect/validation
```

**Basic Usage:**
```php
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

// Define validation rules
$validator = v::attribute('name', v::stringType()->length(1, 255))
              ->attribute('email', v::email())
              ->attribute('age', v::numeric()->between(18, 120));

// Validate data
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
];

try {
    $validator->assert($data);
    echo "Data is valid!";
} catch (NestedValidationException $e) {
    print_r($e->getMessages());
}

// Chain validation
v::stringType()->alnum()->noWhitespace()->length(1, 15)->validate('username');
```

**Alternative: Opis/JSON Schema**
**Package:** `opis/json-schema`
**Latest Version:** ^2.3.0
**PHP Requirements:** PHP 8.0+

**Alternative: Symfony Validator Component**
**Package:** `symfony/validator`
**Latest Version:** ^7.0.0
**PHP Requirements:** PHP 8.1+

### Why Respect/Validation?
- **Zod-like Syntax**: Fluent interface similar to Zod
- **Comprehensive**: 100+ built-in validators
- **Extensible**: Easy to create custom validators
- **Performance**: Optimized for speed
- **Integration**: Works well with frameworks

## 3. UUID Generation: uuid (v11.1.0) → PHP Alternatives

### Primary Recommendation: Ramsey/UUID
**Package:** `ramsey/uuid`
**Latest Version:** ^4.7.0
**PHP Requirements:** PHP 8.0+
**Composer Support:** Yes

```bash
composer require ramsey/uuid
```

**Basic Usage:**
```php
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

// Generate UUID v4 (random)
$uuid4 = Uuid::uuid4();
echo $uuid4->toString(); // "6ba7b810-9dad-11d1-80b4-00c04fd430c8"

// Generate UUID v1 (time-based)
$uuid1 = Uuid::uuid1();
echo $uuid1->toString();

// Generate UUID v5 (namespace-based)
$uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'example.com');
echo $uuid5->toString();

// Validate UUID
if (Uuid::isValid('6ba7b810-9dad-11d1-80b4-00c04fd430c8')) {
    echo "Valid UUID";
}

// UUID to binary and back
$binary = $uuid4->getBytes();
$restored = Uuid::fromBytes($binary);
```

**With Doctrine Integration:**
**Package:** `ramsey/uuid-doctrine`
**Latest Version:** ^2.0.0

### Why Ramsey/UUID?
- **RFC 4122 Compliant**: Full UUID specification support
- **Multiple Versions**: Supports v1, v3, v4, v5
- **Performance**: Optimized implementations
- **Framework Integration**: Doctrine, Symfony, Laravel support
- **Extensive Documentation**: Well-documented with examples

## 4. JSON Patch (RFC 6902): fast-json-patch (v3.1.1) → PHP Alternatives

### Primary Recommendation: Swaggest/Json-Diff
**Package:** `swaggest/json-diff`
**Latest Version:** ^3.12.0
**PHP Requirements:** PHP 8.0+
**Composer Support:** Yes

```bash
composer require swaggest/json-diff
```

**Basic Usage:**
```php
use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPatch;

// Original JSON
$original = ['name' => 'John', 'age' => 30];

// Modified JSON
$modified = ['name' => 'John Doe', 'age' => 31, 'city' => 'New York'];

// Create JSON diff
$diff = new JsonDiff($original, $modified);
$patch = $diff->getPatch();

// Export as JSON Patch
$patchJson = json_encode($patch, JSON_PRETTY_PRINT);
echo $patchJson;

// Apply patch
$jsonPatch = new JsonPatch();
$jsonPatch->import($patchJson);

$target = $original;
$jsonPatch->apply($target);

// Result: ['name' => 'John Doe', 'age' => 31, 'city' => 'New York']
```

**Alternative: Custom Implementation**
For simpler cases, you can implement JSON Patch operations manually using PHP's array functions.

### Why Swaggest/Json-Diff?
- **RFC 6902 Compliant**: Full JSON Patch specification support
- **Performance**: Efficient diff and patch operations
- **Comprehensive**: Supports all JSON Patch operations
- **Well-Maintained**: Active development and good test coverage
- **Additional Features**: JSON Merge Patch support

## 5. Protocol Buffers: @bufbuild/protobuf (v2.2.5) → PHP Alternatives

### Primary Recommendation: Google/Protobuf
**Package:** `google/protobuf`
**Latest Version:** ^3.25.0
**PHP Requirements:** PHP 7.4+ (PHP 8.1+ recommended)
**Composer Support:** Yes

```bash
composer require google/protobuf
```

**Basic Usage:**
```php
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\MapField;

// Generate PHP classes from .proto files
// protoc --php_out=. your_schema.proto

// Using generated classes
$message = new YourMessage();
$message->setId(123);
$message->setName('Test Message');
$message->setActive(true);

// Serialize to binary
$binary = $message->serializeToString();

// Deserialize from binary
$restored = new YourMessage();
$restored->mergeFromString($binary);

// JSON support
$json = $message->serializeToJsonString();
$fromJson = new YourMessage();
$fromJson->mergeFromJsonString($json);
```

**Optional PHP Extension:**
**Package:** `ext-protobuf`
**Purpose:** C extension for better performance

### Why Google/Protobuf?
- **Official Implementation**: Maintained by Google
- **Full Protocol Support**: Complete Protocol Buffers feature set
- **Performance**: Optional C extension for high performance
- **Cross-Language**: Compatible with other language implementations
- **Well-Documented**: Extensive documentation and examples

## 6. Additional Recommendations

### For Observable Pattern: Generator-based Approach
PHP 8.1+ generators provide a lightweight alternative to RxJS observables:

```php
function eventGenerator(): Generator {
    yield new Event('start', ['data' => 'initial']);
    yield new Event('progress', ['percent' => 50]);
    yield new Event('complete', ['result' => 'done']);
}

// Usage
foreach (eventGenerator() as $event) {
    echo $event->getType() . ': ' . json_encode($event->getData()) . "\n";
}
```

### For HTTP Streaming: React/HTTP
**Package:** `react/http`
**Latest Version:** ^1.9.0

```bash
composer require react/http
```

## Summary Matrix

| TypeScript | PHP Equivalent | Package | Version | PHP Min | Notes |
|------------|----------------|---------|---------|---------|-------|
| rxjs | React/Promise | react/promise | ^3.2.0 | 7.1+ | Active maintenance |
| zod | Respect/Validation | respect/validation | ^2.3.0 | 8.0+ | Zod-like syntax |
| uuid | Ramsey/UUID | ramsey/uuid | ^4.7.0 | 8.0+ | RFC 4122 compliant |
| fast-json-patch | Swaggest/Json-Diff | swaggest/json-diff | ^3.12.0 | 8.0+ | RFC 6902 compliant |
| @bufbuild/protobuf | Google/Protobuf | google/protobuf | ^3.25.0 | 7.4+ | Official implementation |

## Installation Commands

Complete setup for all dependencies:
```bash
# Core dependencies
composer require react/promise
composer require respect/validation
composer require ramsey/uuid
composer require swaggest/json-diff
composer require google/protobuf

# Optional performance extensions
composer require ramsey/uuid-doctrine
composer require react/http

# Development dependencies
composer require --dev phpunit/phpunit
composer require --dev phpstan/phpstan
composer require --dev squizlabs/php_codesniffer
```

## Migration Considerations

### 1. Promise/A+ vs RxJS
- PHP promises are simpler than RxJS observables
- Use generators for streaming scenarios
- React/Promise provides a solid foundation

### 2. Validation Patterns
- Respect/Validation has a similar fluent API to Zod
- Consider creating custom validators for complex schemas
- Integration with framework validation systems available

### 3. UUID Handling
- Ramsey/UUID provides all UUID versions
- Doctrine integration available for ORM
- Binary serialization support for performance

### 4. JSON Patch Operations
- Swaggest/Json-Diff provides complete RFC 6902 support
- Performance is good for most use cases
- Consider custom implementation for simple cases

### 5. Protocol Buffers
- Google/Protobuf is the official implementation
- Code generation required from .proto files
- Optional C extension for high-performance scenarios

## Performance Considerations

1. **Memory Usage**: PHP packages are generally memory-efficient
2. **CPU Performance**: Consider C extensions for high-throughput scenarios
3. **Bundle Size**: PHP packages have minimal overhead compared to JavaScript
4. **Startup Time**: Composer autoloading is efficient

## Conclusion

The identified PHP packages provide robust, production-ready equivalents to the TypeScript dependencies. All packages are actively maintained, well-documented, and have good community support. The migration path is straightforward with minimal functional differences.
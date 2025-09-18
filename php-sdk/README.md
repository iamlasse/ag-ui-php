# AG-UI PHP SDK

The PHP SDK for AG-UI - An event-based protocol that standardizes agent-user interactions.

## Overview

AG-UI provides a standardized protocol for communication between AI agents and user interfaces. This PHP SDK implements the core protocol, client libraries, and framework integrations.

## Features

- **Event-Driven Communication**: All agent-UI communication happens through typed events
- **Transport Agnostic**: Supports SSE, WebSockets, HTTP binary, and custom transports
- **Framework Integrations**: Easy integration with popular PHP frameworks
- **Observable Pattern**: Stream agent responses efficiently
- **Multiple Sequential Runs**: Support for multiple agent runs in a single session

## Installation

```bash
composer require ag-ui/php-sdk
```

## Quick Start

```php
use AGUI\Client\HttpAgent;

$agent = new HttpAgent([
    'endpoint' => 'https://api.example.com/agent',
    'apiKey' => 'your-api-key'
]);

$events = $agent->run([
    'input' => 'Hello, world!'
]);

foreach ($events as $event) {
    echo $event->getType() . ': ' . $event->getData() . "\n";
}
```

## Structure

```
php-sdk/
├── packages/           # Core packages
│   ├── core/          # Core protocol implementation
│   ├── client/        # HTTP client and agents
│   ├── proto/         # Protocol definitions and messages
│   ├── encoder/       # Event encoding/decoding
│   └── cli/           # Command-line interface
├── integrations/      # Framework integrations
├── examples/dojo/     # Demo application
└── composer.json
```

## Development

### Requirements

- PHP 8.1+
- Composer

### Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run linting
composer lint

# Static analysis
composer analyze
```

## Contributing

Please see [CONTRIBUTING.md](../../CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License - see the [LICENSE](../../LICENSE) file for details.

## Support

- [Documentation](https://docs.ag-ui.org)
- [Issues](https://github.com/ag-ui/ag-ui/issues)
- [Discussions](https://github.com/ag-ui/ag-ui/discussions)
# AG-UI Dojo Demo

A demonstration application showcasing AG-UI protocol features and capabilities.

## Features

- **Agent Chat**: Real-time conversations with AI agents
- **Generative UI**: Dynamic interface generation based on agent responses
- **Human-in-the-Loop**: Interactive workflows requiring human input
- **Multi-Run Support**: Multiple sequential agent runs in single session
- **Framework Integration**: Example implementations across platforms

## Setup

```bash
# Install dependencies
composer install

# Start development server
composer start
```

## Access

Open http://localhost:8000 in your browser to access the demo.

## Structure

```
dojo/
├── src/
│   ├── Controller/    # HTTP controllers
│   ├── Service/      # Business logic
│   └── Entity/       # Data models
├── public/           # Web root
├── templates/        # View templates
├── config/           # Configuration
└── tests/            # Test suite
```

## Examples

The demo includes examples of:

1. **Basic Agent Communication**: Simple Q&A with AI agents
2. **Tool Usage**: Agents calling external tools and APIs
3. **State Management**: Maintaining conversation state across runs
4. **Error Handling**: Graceful handling of agent errors and timeouts
5. **Multiple Agents**: Coordinating between different specialized agents

## Development

```bash
# Run tests
composer test

# Static analysis
composer analyze
```
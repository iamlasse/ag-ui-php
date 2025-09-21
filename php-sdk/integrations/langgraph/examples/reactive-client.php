<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use AGUI\Integrations\LangGraph\LangGraphAgent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;
use GuzzleHttp\Client;

/**
 * Reactive LangGraph Client Example
 *
 * This example demonstrates a more reactive approach to handling LangGraph
 * events with proper error handling and state management.
 */

class ReactiveLangGraphClient
{
    private LangGraphAgent $agent;
    private array $eventHandlers = [];
    private array $conversationHistory = [];
    private array $tools = [];

    public function __construct(array $config = [])
    {
        // Initialize tools
        $this->tools = [
            $this->createWeatherTool(),
            $this->createCalculatorTool(),
            $this->createSearchTool(),
        ];

        // Create agent
        $this->agent = new LangGraphAgent([
            'langGraphUrl' => $config['langGraphUrl'] ?? 'http://localhost:8000',
            'apiKey' => $config['apiKey'] ?? '',
            'assistantId' => $config['assistantId'] ?? 'reactive-assistant',
            'threadId' => $config['threadId'] ?? 'reactive-' . uniqid(),
            'description' => 'Reactive LangGraph client with multiple capabilities',
            'debug' => $config['debug'] ?? false,
        ]);

        // Set up event handlers
        $this->setupEventHandlers();
    }

    private function createWeatherTool(): Tool
    {
        $tool = new Tool();
        $tool->name = 'get_weather';
        $tool->description = 'Get the current weather for a location';
        $tool->parameters = (object) [
            'type' => 'object',
            'properties' => (object) [
                'location' => (object) [
                    'type' => 'string',
                    'description' => 'The city and state, e.g. San Francisco, CA'
                ],
                'unit' => (object) [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'Temperature unit (default: celsius)'
                ]
            ],
            'required' => ['location']
        ];
        return $tool;
    }

    private function createCalculatorTool(): Tool
    {
        $tool = new Tool();
        $tool->name = 'calculate';
        $tool->description = 'Perform mathematical calculations';
        $tool->parameters = (object) [
            'type' => 'object',
            'properties' => (object) [
                'expression' => (object) [
                    'type' => 'string',
                    'description' => 'The mathematical expression to calculate (e.g., "2 + 2 * 3")'
                ]
            ],
            'required' => ['expression']
        ];
        return $tool;
    }

    private function createSearchTool(): Tool
    {
        $tool = new Tool();
        $tool->name = 'web_search';
        $tool->description = 'Search the web for information';
        $tool->parameters = (object) [
            'type' => 'object',
            'properties' => (object) [
                'query' => (object) [
                    'type' => 'string',
                    'description' => 'The search query'
                ],
                'max_results' => (object) [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (default: 5)',
                    'default' => 5
                ]
            ],
            'required' => ['query']
        ];
        return $tool;
    }

    private function setupEventHandlers(): void
    {
        $this->agent->subscribe([
            'onRunStarted' => function ($params) {
                $this->handleEvent('run_started', $params);
                echo "🚀 Run started: {$params->runId}\n";
            },

            'onRunFinished' => function ($params) {
                $this->handleEvent('run_finished', $params);
                echo "✅ Run completed successfully\n";
            },

            'onRunFailed' => function ($params) {
                $this->handleEvent('run_failed', $params);
                echo "❌ Run failed: {$params->error->getMessage()}\n";
            },

            'onNewMessage' => function ($params) {
                $this->handleEvent('new_message', $params);
                $this->addToHistory($params->message);

                $roleIcon = match($params->message->role) {
                    'user' => '👤',
                    'assistant' => '🤖',
                    'system' => '⚙️',
                    'tool' => '🔧',
                    default => '💬'
                };

                echo "{$roleIcon} [{$params->message->role}] {$params->message->content}\n";

                if ($params->message->toolCalls) {
                    foreach ($params->message->toolCalls as $toolCall) {
                        echo "   🛠️  Tool: {$toolCall->function->name}\n";
                    }
                }
            },

            'onNewToolCall' => function ($params) {
                $this->handleEvent('tool_call', $params);
                echo "🔧 Tool called: {$params->toolCall->function->name}\n";
                echo "   Arguments: " . json_encode($params->toolCall->function->arguments) . "\n";
            },

            'onStateChanged' => function ($params) {
                $this->handleEvent('state_changed', $params);
                if ($this->agent->debug) {
                    echo "📊 State updated\n";
                }
            },

            'onMessagesChanged' => function ($params) {
                $this->handleEvent('messages_changed', $params);
                if ($this->agent->debug) {
                    echo "📝 Messages updated (count: " . count($params->messages) . ")\n";
                }
            },
        ]);
    }

    private function handleEvent(string $eventType, $params): void
    {
        if (!isset($this->eventHandlers[$eventType])) {
            $this->eventHandlers[$eventType] = [];
        }

        foreach ($this->eventHandlers[$eventType] as $handler) {
            try {
                $handler($params);
            } catch (Exception $e) {
                error_log("Error in event handler for {$eventType}: " . $e->getMessage());
            }
        }
    }

    private function addToHistory(Message $message): void
    {
        $this->conversationHistory[] = [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'timestamp' => time(),
        ];
    }

    public function addEventHandler(string $eventType, callable $handler): self
    {
        if (!isset($this->eventHandlers[$eventType])) {
            $this->eventHandlers[$eventType] = [];
        }
        $this->eventHandlers[$eventType][] = $handler;
        return $this;
    }

    public function sendMessage(string $content, array $options = []): array
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Sending message: {$content}\n";
        echo str_repeat("=", 50) . "\n\n";

        // Create user message
        $userMessage = new Message([
            'id' => 'msg-' . uniqid(),
            'role' => 'user',
            'content' => $content,
        ]);

        // Add message to agent
        $this->agent->addMessage($userMessage);

        try {
            // Run the agent
            $result = $this->agent->runAgent([
                'tools' => $this->tools,
                'context' => $options['context'] ?? [],
                'forwardedProps' => array_merge($options['forwardedProps'] ?? [], [
                    'nodeName' => 'reactive-client',
                    'clientType' => 'php',
                    'timestamp' => time(),
                ])
            ]);

            return [
                'success' => true,
                'newMessages' => $result->newMessages,
                'finalState' => $this->agent->state,
                'conversationHistory' => $this->conversationHistory,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'conversationHistory' => $this->conversationHistory,
            ];
        }
    }

    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }

    public function getAgent(): LangGraphAgent
    {
        return $this->agent;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function clearConversation(): self
    {
        $this->agent->setMessages([]);
        $this->conversationHistory = [];
        return $this;
    }

    public function exportConversation(): string
    {
        return json_encode([
            'threadId' => $this->agent->threadId,
            'history' => $this->conversationHistory,
            'tools' => array_map(fn($tool) => [
                'name' => $tool->name,
                'description' => $tool->description,
            ], $this->tools),
            'timestamp' => time(),
        ], JSON_PRETTY_PRINT);
    }
}

// Example usage
try {
    // Create reactive client
    $client = new ReactiveLangGraphClient([
        'langGraphUrl' => 'http://localhost:8000',
        'apiKey' => 'your-api-key-here',
        'debug' => true,
    ]);

    // Add custom event handlers
    $client->addEventHandler('run_started', function ($params) {
        echo "Custom handler: Run started at " . date('Y-m-d H:i:s') . "\n";
    });

    $client->addEventHandler('new_message', function ($params) {
        if ($params->message->role === 'assistant') {
            echo "Assistant response received!\n";
        }
    });

    // Interactive conversation loop
    echo "🤖 LangGraph Reactive Client\n";
    echo "Type 'help' for commands, 'quit' to exit\n\n";

    while (true) {
        $input = trim(readline("You: "));

        if ($input === 'quit' || $input === 'exit') {
            break;
        }

        if ($input === 'help') {
            echo "Available commands:\n";
            echo "  help     - Show this help\n";
            echo "  history  - Show conversation history\n";
            echo "  clear    - Clear conversation\n";
            echo "  export   - Export conversation\n";
            echo "  tools    - Show available tools\n";
            echo "  quit     - Exit\n";
            continue;
        }

        if ($input === 'history') {
            echo "\n=== CONVERSATION HISTORY ===\n";
            foreach ($client->getConversationHistory() as $msg) {
                $time = date('H:i:s', $msg['timestamp']);
                echo "[{$time}] [{$msg['role']}] {$msg['content']}\n";
            }
            echo "===============================\n\n";
            continue;
        }

        if ($input === 'clear') {
            $client->clearConversation();
            echo "✅ Conversation cleared\n";
            continue;
        }

        if ($input === 'export') {
            echo "\n=== CONVERSATION EXPORT ===\n";
            echo $client->exportConversation() . "\n";
            echo "=============================\n\n";
            continue;
        }

        if ($input === 'tools') {
            echo "\n=== AVAILABLE TOOLS ===\n";
            foreach ($client->getTools() as $tool) {
                echo "• {$tool->name}: {$tool->description}\n";
            }
            echo "=========================\n\n";
            continue;
        }

        if (empty($input)) {
            continue;
        }

        // Send message and get response
        $result = $client->sendMessage($input);

        if (!$result['success']) {
            echo "❌ Error: {$result['error']}\n";
        }
    }

    echo "\n👋 Goodbye!\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
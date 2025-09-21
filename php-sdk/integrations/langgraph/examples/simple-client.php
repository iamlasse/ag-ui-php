<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use AGUI\Integrations\LangGraph\LangGraphAgent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;

/**
 * Simple LangGraph Client Example
 *
 * This example demonstrates how to use the LangGraphAgent to connect
 * to a LangGraph service and stream events back to the client.
 */

// Create a simple message function tool
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

// Create a calculator tool
$calculatorTool = new Tool();
$calculatorTool->name = 'calculate';
$calculatorTool->description = 'Perform a mathematical calculation';
$calculatorTool->parameters = (object) [
    'type' => 'object',
    'properties' => (object) [
        'expression' => (object) [
            'type' => 'string',
            'description' => 'The mathematical expression to calculate'
        ]
    ],
    'required' => ['expression']
];

// Create the LangGraph agent
$agent = new LangGraphAgent([
    'langGraphUrl' => 'http://localhost:8000',
    'apiKey' => 'your-api-key-here',
    'assistantId' => 'weather-assistant',
    'threadId' => 'thread-' . uniqid(),
    'description' => 'Weather and calculation assistant',
    'debug' => true,
]);

// Create initial messages
$messages = [
    new Message([
        'id' => 'msg-1',
        'role' => 'system',
        'content' => 'You are a helpful assistant that can provide weather information and perform calculations.',
    ]),
    new Message([
        'id' => 'msg-2',
        'role' => 'user',
        'content' => 'What\'s the weather like in San Francisco, CA?',
    ]),
];

// Define available tools
$tools = [$weatherTool, $calculatorTool];

// Add a subscriber to handle events
$agent->subscribe([
    'onRunStarted' => function ($params) {
        echo "=== RUN STARTED ===\n";
        echo "Thread ID: {$params->threadId}\n";
        echo "Run ID: {$params->runId}\n";
        echo "\n";
    },

    'onRunFinished' => function ($params) {
        echo "=== RUN FINISHED ===\n";
        echo "Result: " . json_encode($params->result) . "\n";
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

    'onStateChanged' => function ($params) {
        echo "[STATE_UPDATE] State changed\n";
    },

    'onRunFailed' => function ($params) {
        echo "=== RUN FAILED ===\n";
        echo "Error: {$params->error->getMessage()}\n";
        echo "\n";
    },
]);

// Run the agent
try {
    echo "Starting LangGraph Agent...\n";
    echo "Agent ID: {$agent->agentId}\n";
    echo "Thread ID: {$agent->threadId}\n";
    echo "LangGraph URL: {$agent->getLangGraphUrl()}\n";
    echo "\n";

    $result = $agent->runAgent([
        'tools' => $tools,
        'context' => [],
        'forwardedProps' => [
            'nodeName' => 'simple-client',
            'threadMetadata' => [
                'source' => 'php-example',
                'version' => '1.0.0'
            ]
        ]
    ]);

    echo "=== FINAL RESULT ===\n";
    echo "New messages count: " . count($result->newMessages) . "\n";
    echo "Final state: " . json_encode($agent->state) . "\n";

    // Display all messages
    echo "\n=== CONVERSATION HISTORY ===\n";
    foreach ($agent->messages as $message) {
        echo "[{$message->role}] {$message->content}\n";
        if (isset($message->toolCalls)) {
            foreach ($message->toolCalls as $toolCall) {
                echo "  -> Tool: {$toolCall->function->name}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error running agent: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nExample completed.\n";
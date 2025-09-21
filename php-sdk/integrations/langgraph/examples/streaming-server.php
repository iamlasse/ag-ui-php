<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use AGUI\Integrations\LangGraph\LangGraphAgent;
use AGUI\Core\Types\Message;
use AGUI\Core\Types\Tool;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Socket\SocketServer;
use React\EventLoop\Loop;

/**
 * Streaming LangGraph Server Example
 *
 * This example demonstrates how to create a simple HTTP server that
 * can handle streaming LangGraph requests and serve Server-Sent Events (SSE).
 */

// Create tools
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

// Tools array
$tools = [$weatherTool, $calculatorTool];

// Create the event loop
$loop = Loop::get();

// Create the HTTP server
$server = new HttpServer($loop, function (ServerRequest $request) use ($tools) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    // Handle CORS preflight requests
    if ($method === 'OPTIONS') {
        return new Response(
            200,
            [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ],
            ''
        );
    }

    // Health check endpoint
    if ($path === '/health') {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['status' => 'healthy', 'timestamp' => time()])
        );
    }

    // Info endpoint
    if ($path === '/info') {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'name' => 'LangGraph PHP Server',
                'version' => '1.0.0',
                'endpoints' => ['/health', '/info', '/chat', '/stream'],
                'tools' => array_map(fn($tool) => $tool->name, $tools)
            ])
        );
    }

    // Chat endpoint (non-streaming)
    if ($path === '/chat' && $method === 'POST') {
        try {
            $body = json_decode($request->getBody()->getContents(), true);

            if (!isset($body['message']) || !isset($body['thread_id'])) {
                return new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'Missing required fields: message, thread_id'])
                );
            }

            // Create agent
            $agent = new LangGraphAgent([
                'langGraphUrl' => $body['langgraph_url'] ?? 'http://localhost:8000',
                'apiKey' => $body['api_key'] ?? '',
                'assistantId' => $body['assistant_id'] ?? 'default',
                'threadId' => $body['thread_id'],
                'debug' => $body['debug'] ?? false,
            ]);

            // Create messages
            $messages = [
                new Message([
                    'id' => 'msg-' . uniqid(),
                    'role' => 'user',
                    'content' => $body['message'],
                ])
            ];

            // Set up event collection
            $events = [];
            $agent->subscribe([
                'onNewMessage' => function ($params) use (&$events) {
                    $events[] = [
                        'type' => 'message',
                        'message' => [
                            'id' => $params->message->id,
                            'role' => $params->message->role,
                            'content' => $params->message->content,
                        ]
                    ];
                },
                'onNewToolCall' => function ($params) use (&$events) {
                    $events[] = [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => $params->toolCall->id,
                            'name' => $params->toolCall->function->name,
                            'arguments' => $params->toolCall->function->arguments,
                        ]
                    ];
                },
            ]);

            // Run the agent
            $result = $agent->runAgent([
                'tools' => $tools,
                'context' => [],
            ]);

            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'success' => true,
                    'thread_id' => $agent->threadId,
                    'events' => $events,
                    'final_messages' => array_map(function($msg) {
                        return [
                            'id' => $msg->id,
                            'role' => $msg->role,
                            'content' => $msg->content,
                        ];
                    }, $result->newMessages),
                ])
            );

        } catch (Exception $e) {
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => $e->getMessage()])
            );
        }
    }

    // Streaming endpoint
    if ($path === '/stream' && $method === 'POST') {
        try {
            $body = json_decode($request->getBody()->getContents(), true);

            if (!isset($body['message']) || !isset($body['thread_id'])) {
                return new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'Missing required fields: message, thread_id'])
                );
            }

            // Return SSE response
            return new Response(
                200,
                [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                ],
                function () use ($body, $tools) {
                    try {
                        // Create agent
                        $agent = new LangGraphAgent([
                            'langGraphUrl' => $body['langgraph_url'] ?? 'http://localhost:8000',
                            'apiKey' => $body['api_key'] ?? '',
                            'assistantId' => $body['assistant_id'] ?? 'default',
                            'threadId' => $body['thread_id'],
                            'debug' => $body['debug'] ?? false,
                        ]);

                        // Create messages
                        $messages = [
                            new Message([
                                'id' => 'msg-' . uniqid(),
                                'role' => 'user',
                                'content' => $body['message'],
                            ])
                        ];

                        // Set up streaming subscriber
                        $agent->subscribe([
                            'onRunStarted' => function ($params) {
                                echo "data: " . json_encode([
                                    'event' => 'run_started',
                                    'thread_id' => $params->threadId,
                                    'run_id' => $params->runId,
                                    'timestamp' => time(),
                                ]) . "\n\n";
                                flush();
                            },

                            'onNewMessage' => function ($params) {
                                echo "data: " . json_encode([
                                    'event' => 'message',
                                    'message' => [
                                        'id' => $params->message->id,
                                        'role' => $params->message->role,
                                        'content' => $params->message->content,
                                    ]
                                ]) . "\n\n";
                                flush();
                            },

                            'onNewToolCall' => function ($params) {
                                echo "data: " . json_encode([
                                    'event' => 'tool_call',
                                    'tool_call' => [
                                        'id' => $params->toolCall->id,
                                        'name' => $params->toolCall->function->name,
                                        'arguments' => $params->toolCall->function->arguments,
                                    ]
                                ]) . "\n\n";
                                flush();
                            },

                            'onRunFinished' => function ($params) {
                                echo "data: " . json_encode([
                                    'event' => 'run_finished',
                                    'result' => $params->result,
                                    'timestamp' => time(),
                                ]) . "\n\n";
                                flush();
                            },

                            'onRunFailed' => function ($params) {
                                echo "data: " . json_encode([
                                    'event' => 'run_error',
                                    'error' => $params->error->getMessage(),
                                    'timestamp' => time(),
                                ]) . "\n\n";
                                flush();
                            },
                        ]);

                        // Run the agent
                        $result = $agent->runAgent([
                            'tools' => $tools,
                            'context' => [],
                        ]);

                        // Send completion event
                        echo "data: " . json_encode([
                            'event' => 'stream_complete',
                            'thread_id' => $agent->threadId,
                            'timestamp' => time(),
                        ]) . "\n\n";
                        flush();

                    } catch (Exception $e) {
                        echo "data: " . json_encode([
                            'event' => 'error',
                            'error' => $e->getMessage(),
                            'timestamp' => time(),
                        ]) . "\n\n";
                        flush();
                    }
                }
            );

        } catch (Exception $e) {
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => $e->getMessage()])
            );
        }
    }

    // Default 404 response
    return new Response(
        404,
        ['Content-Type' => 'application/json'],
        json_encode(['error' => 'Not found'])
    );
});

// Create socket server
$socket = new SocketServer($loop, '0.0.0.0:8080');

// Start the server
echo "LangGraph PHP Server started on http://0.0.0.0:8080\n";
echo "Available endpoints:\n";
echo "  GET  /health     - Health check\n";
echo "  GET  /info       - Server information\n";
echo "  POST /chat       - Non-streaming chat\n";
echo "  POST /stream     - Streaming chat (SSE)\n";
echo "\n";

$server->listen($socket);

// Run the event loop
$loop->run();
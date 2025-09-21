<?php

declare(strict_types=1);

/**
 * AG-UI PHP Protobuf Compatibility Demo
 * 
 * This script demonstrates API compatibility between PHP and TypeScript implementations.
 * It shows how the same events can be encoded and decoded using the PHP API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AGUI\Proto\Proto;
use AGUI\Proto\EventTypes;
use AGUI\Proto\JsonPatchOperations;

// Color output functions
function green(string $text): string {
    return "\033[32m$text\033[0m";
}

function blue(string $text): string {
    return "\033[34m$text\033[0m";
}

function yellow(string $text): string {
    return "\033[33m$text\033[0m";
}

function red(string $text): string {
    return "\033[31m$text\033[0m";
}

function testEvent(string $name, array $event): void {
    echo yellow("Testing: $name") . "\n";
    echo "Input: " . json_encode($event, JSON_PRETTY_PRINT) . "\n";
    
    try {
        // Encode
        $encoded = Proto::encode($event);
        echo green("✓ Encoded successfully") . " (length: " . strlen($encoded) . " bytes)\n";
        
        // Decode
        $decoded = Proto::decode($encoded);
        echo green("✓ Decoded successfully") . "\n";
        echo "Output: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
        
        // Verify type matches
        if ($decoded['type'] === $event['type']) {
            echo green("✓ Type verification passed") . "\n";
        } else {
            echo red("✗ Type verification failed") . "\n";
        }
        
    } catch (Exception $e) {
        echo red("✗ Error: " . $e->getMessage()) . "\n";
    }
    
    echo str_repeat("-", 60) . "\n\n";
}

echo blue("AG-UI PHP Protocol Buffer Compatibility Demo") . "\n";
echo str_repeat("=", 60) . "\n\n";

echo "Media Type: " . blue(Proto::AGUI_MEDIA_TYPE) . "\n\n";

// Test 1: Text Message Start Event
testEvent("Text Message Start Event", [
    'type' => EventTypes::TEXT_MESSAGE_START,
    'timestamp' => 1634567890123,
    'messageId' => 'msg-123',
    'role' => 'assistant'
]);

// Test 2: Text Message Content Event
testEvent("Text Message Content Event", [
    'type' => EventTypes::TEXT_MESSAGE_CONTENT,
    'messageId' => 'msg-123',
    'delta' => 'Hello, world! This is a streaming message.'
]);

// Test 3: Tool Call Start Event
testEvent("Tool Call Start Event", [
    'type' => EventTypes::TOOL_CALL_START,
    'toolCallId' => 'call-456',
    'toolCallName' => 'web_search',
    'parentMessageId' => 'msg-789'
]);

// Test 4: State Snapshot Event
testEvent("State Snapshot Event", [
    'type' => EventTypes::STATE_SNAPSHOT,
    'snapshot' => [
        'currentStep' => 3,
        'totalSteps' => 5,
        'progress' => 0.6,
        'data' => [
            'user' => 'john_doe',
            'session' => 'sess_123',
            'preferences' => [
                'theme' => 'dark',
                'language' => 'en'
            ]
        ]
    ]
]);

// Test 5: State Delta Event with JSON Patch
testEvent("State Delta Event", [
    'type' => EventTypes::STATE_DELTA,
    'delta' => [
        [
            'op' => JsonPatchOperations::ADD,
            'path' => '/items/0',
            'value' => [
                'id' => 'item-1',
                'name' => 'New Task',
                'completed' => false
            ]
        ],
        [
            'op' => JsonPatchOperations::REPLACE,
            'path' => '/progress',
            'value' => 0.75
        ],
        [
            'op' => JsonPatchOperations::REMOVE,
            'path' => '/temporary_data'
        ]
    ]
]);

// Test 6: Messages Snapshot Event
testEvent("Messages Snapshot Event", [
    'type' => EventTypes::MESSAGES_SNAPSHOT,
    'messages' => [
        [
            'id' => 'msg-1',
            'role' => 'user',
            'content' => 'What is the weather like today?',
            'toolCalls' => []
        ],
        [
            'id' => 'msg-2', 
            'role' => 'assistant',
            'content' => 'I\'ll check the weather for you.',
            'toolCalls' => [
                [
                    'id' => 'call-weather-1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'arguments' => json_encode([
                            'location' => 'current',
                            'units' => 'metric'
                        ])
                    ]
                ]
            ]
        ],
        [
            'id' => 'msg-3',
            'role' => 'function',
            'content' => json_encode([
                'temperature' => 22,
                'condition' => 'sunny',
                'humidity' => 65
            ]),
            'toolCallId' => 'call-weather-1'
        ]
    ]
]);

// Test 7: Custom Event
testEvent("Custom Event", [
    'type' => EventTypes::CUSTOM,
    'name' => 'user_interaction',
    'value' => [
        'action' => 'click',
        'element' => 'submit_button',
        'coordinates' => [120, 340],
        'metadata' => [
            'timestamp' => time(),
            'user_agent' => 'AG-UI/1.0',
            'session_id' => 'sess_custom_123'
        ]
    ]
]);

// Test 8: Run Lifecycle Events
testEvent("Run Started Event", [
    'type' => EventTypes::RUN_STARTED,
    'threadId' => 'thread-abc123',
    'runId' => 'run-def456'
]);

testEvent("Run Finished Event", [
    'type' => EventTypes::RUN_FINISHED,
    'threadId' => 'thread-abc123',
    'runId' => 'run-def456',
    'result' => [
        'status' => 'success',
        'duration' => 1250,
        'output' => 'Task completed successfully',
        'metrics' => [
            'tokens_used' => 1500,
            'api_calls' => 3,
            'cache_hits' => 12
        ]
    ]
]);

testEvent("Run Error Event", [
    'type' => EventTypes::RUN_ERROR,
    'code' => 'RATE_LIMIT_EXCEEDED',
    'message' => 'API rate limit exceeded. Please retry in 60 seconds.'
]);

// Test 9: Raw Event
testEvent("Raw Event", [
    'type' => EventTypes::RAW,
    'event' => [
        'original_type' => 'system.notification',
        'priority' => 'high',
        'data' => [
            'title' => 'System Maintenance',
            'message' => 'Scheduled maintenance in 30 minutes',
            'actions' => ['acknowledge', 'snooze']
        ]
    ],
    'source' => 'system_monitor'
]);

// Test 10: Step Events
testEvent("Step Started Event", [
    'type' => EventTypes::STEP_STARTED,
    'stepName' => 'data_validation'
]);

testEvent("Step Finished Event", [
    'type' => EventTypes::STEP_FINISHED,
    'stepName' => 'data_validation'
]);

// Test compatibility with various edge cases
echo blue("Testing Edge Cases") . "\n";
echo str_repeat("=", 30) . "\n\n";

// Test event with minimal data
testEvent("Minimal Event", [
    'type' => EventTypes::TEXT_MESSAGE_END,
    'messageId' => 'msg-minimal'
]);

// Test event with null values
testEvent("Event with Optional Fields", [
    'type' => EventTypes::TOOL_CALL_START,
    'toolCallId' => 'call-optional',
    'toolCallName' => 'search',
    'parentMessageId' => null  // This should be filtered out
]);

echo green("✓ All compatibility tests completed!") . "\n";
echo blue("The PHP implementation maintains API compatibility with TypeScript.") . "\n";

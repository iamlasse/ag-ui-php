<?php

declare(strict_types=1);

/**
 * AG-UI PHP Protobuf Simple Usage Example
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AGUI\Proto\Proto;
use AGUI\Proto\EventTypes;

// Create a simple text message event
$event = [
    'type' => EventTypes::TEXT_MESSAGE_START,
    'timestamp' => time() * 1000,
    'messageId' => 'msg-hello-world',
    'role' => 'assistant'
];

echo "Original Event:\n";
echo json_encode($event, JSON_PRETTY_PRINT) . "\n\n";

// Encode to protobuf binary
$encoded = Proto::encode($event);
echo "Encoded to " . strlen($encoded) . " bytes of binary data\n";
echo "Binary data (base64): " . base64_encode($encoded) . "\n\n";

// Decode back to array
$decoded = Proto::decode($encoded);
echo "Decoded Event:\n";
echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";

// Verify they match
if ($decoded['type'] === $event['type'] && $decoded['messageid'] === $event['messageId']) {
    echo "✅ Success! Encoding and decoding works correctly.\n";
} else {
    echo "❌ Error! Data mismatch.\n";
}

echo "\nMedia Type: " . Proto::AGUI_MEDIA_TYPE . "\n";

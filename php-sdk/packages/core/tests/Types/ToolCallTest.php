<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\FunctionCall;
use AGUI\Core\Types\ToolCall;
use AGUI\Core\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\ToolCall
 */
final class ToolCallTest extends TestCase {
    public function testCanBeCreated(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('test-id', 'function', $functionCall);

        $this->assertSame('test-id', $toolCall->id);
        $this->assertSame('function', $toolCall->type);
        $this->assertSame('test_function', $toolCall->function->name);
        $this->assertSame('{"arg": "value"}', $toolCall->function->arguments);
    }

    public function testValidationFailsWithInvalidType(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');

        $this->expectException(ValidationException::class);
        new ToolCall('test-id', 'invalid_type', $functionCall);
    }

    public function testCanBeConvertedToArray(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('test-id', 'function', $functionCall);

        $array = $toolCall->toArray();

        $this->assertSame([
            'id' => 'test-id',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"arg": "value"}'
            ]
        ], $array);
    }

    public function testCanBeConvertedToJson(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $toolCall = new ToolCall('test-id', 'function', $functionCall);

        $json = $toolCall->toJson();

        $this->assertJson($json);
        $expected = '{"id":"test-id","type":"function","function":{"name":"test_function","arguments":"{\"arg\": \"value\"}"}}';
        $this->assertSame($expected, $json);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'id' => 'test-id',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"arg": "value"}'
            ]
        ];

        $toolCall = ToolCall::fromArray($data);

        $this->assertSame('test-id', $toolCall->id);
        $this->assertSame('function', $toolCall->type);
        $this->assertSame('test_function', $toolCall->function->name);
        $this->assertSame('{"arg": "value"}', $toolCall->function->arguments);
    }
}
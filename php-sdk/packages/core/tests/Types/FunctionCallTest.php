<?php

declare(strict_types=1);

namespace AGUI\Tests\Core\Types;

use AGUI\Core\Types\FunctionCall;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AGUI\Core\Types\FunctionCall
 */
final class FunctionCallTest extends TestCase {
    public function testCanBeCreated(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');

        $this->assertSame('test_function', $functionCall->name);
        $this->assertSame('{"arg": "value"}', $functionCall->arguments);
    }

    public function testCanBeCreatedFromArray(): void {
        $data = [
            'name' => 'test_function',
            'arguments' => '{"arg": "value"}'
        ];

        $functionCall = FunctionCall::fromArray($data);

        $this->assertSame('test_function', $functionCall->name);
        $this->assertSame('{"arg": "value"}', $functionCall->arguments);
    }

    public function testCanBeConvertedToArray(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $array = $functionCall->toArray();

        $this->assertSame([
            'name' => 'test_function',
            'arguments' => '{"arg": "value"}'
        ], $array);
    }

    public function testCanBeConvertedToJson(): void {
        $functionCall = new FunctionCall('test_function', '{"arg": "value"}');
        $json = $functionCall->toJson();

        $this->assertJson($json);
        $this->assertSame('{"name":"test_function","arguments":"{\"arg\": \"value\"}"}', $json);
    }

    public function testCanBeCreatedFromJson(): void {
        $json = '{"name":"test_function","arguments":"{\"arg\": \"value\"}"}';
        $functionCall = FunctionCall::fromJson($json);

        $this->assertSame('test_function', $functionCall->name);
        $this->assertSame('{"arg": "value"}', $functionCall->arguments);
    }
}
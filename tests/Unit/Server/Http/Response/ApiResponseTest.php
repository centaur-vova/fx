<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Http\Response;

use App\Contract\Http\HttpStatus;
use App\Exception\Http\BadRequestException;
use App\Server\Http\Response\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function testOk(): void
    {
        $response = ApiResponse::ok('Success');

        $this->assertTrue($response->success);
        $this->assertSame('Success', $response->message);
        $this->assertSame(HttpStatus::OK, $response->status);
        $this->assertNull($response->data);
    }

    public function testOkWithData(): void
    {
        $data = new class () implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['key' => 'value'];
            }
        };

        $response = ApiResponse::ok('Success', $data);

        $this->assertSame($data, $response->data);
    }

    public function testError(): void
    {
        $response = ApiResponse::error('Error', HttpStatus::BAD_REQUEST);

        $this->assertFalse($response->success);
        $this->assertSame('Error', $response->message);
        $this->assertSame(HttpStatus::BAD_REQUEST, $response->status);
    }

    public function testErrorWithData(): void
    {
        $data = new class () implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['error_code' => 123];
            }
        };

        $response = ApiResponse::error('Error', HttpStatus::CONFLICT, $data);

        $this->assertFalse($response->success);
        $this->assertSame('Error', $response->message);
        $this->assertSame(HttpStatus::CONFLICT, $response->status);
        $this->assertSame($data, $response->data);
    }

    public function testFromException(): void
    {
        $exception = new BadRequestException('Invalid input');
        $response = ApiResponse::fromException($exception);

        $this->assertFalse($response->success);
        $this->assertSame('Invalid input', $response->message);
        $this->assertSame(HttpStatus::BAD_REQUEST, $response->status);
        $this->assertNull($response->data);
    }

    public function testFromGenericException(): void
    {
        $exception = new \RuntimeException('Generic error');
        $response = ApiResponse::fromException($exception);

        $this->assertFalse($response->success);
        $this->assertSame('Generic error', $response->message);
        $this->assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->status);
    }

    public function testJsonSerializeWithoutData(): void
    {
        $response = ApiResponse::ok('Done');
        $expected = ['success' => true, 'message' => 'Done'];

        $this->assertSame($expected, $response->jsonSerialize());
    }

    public function testJsonSerializeWithData(): void
    {
        $data = new class () implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['nested' => 'value'];
            }
        };

        $response = ApiResponse::ok('Done', $data);
        $expected = [
            'success' => true,
            'message' => 'Done',
            'data' => ['nested' => 'value'],
        ];

        $this->assertSame($expected, $response->jsonSerialize());
    }
}

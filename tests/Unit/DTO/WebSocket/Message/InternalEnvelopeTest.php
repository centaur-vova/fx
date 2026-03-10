<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\DTO\WebSocket\Message\InternalEnvelope;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

class InternalEnvelopeTest extends TestCase
{
    public function test_it_correctly_wraps_and_serializes_binary_payload(): void
    {
        // Create binary data
        $statusUpdate = TaskStatusUpdate::completed(123, 7, 2);
        $binaryPayload = $statusUpdate->toBinary(); // 13 bytes

        // Wrap wth the internal envelope
        $envelope = InternalEnvelope::wrap('status.changed', $statusUpdate);

        // Serialize to send via pipeMessage
        $serialized = $envelope->serialize();
        $this->assertIsString($serialized);

        // Create from serialized
        $restored = InternalEnvelope::fromSerialized($serialized);

        $this->assertInstanceOf(InternalEnvelope::class, $restored);
        $this->assertEquals('status.changed', $restored->action);

        // Make sure byte offsets are ok
        $this->assertSame(
            bin2hex($binaryPayload),
            bin2hex($restored->getFinalPayload()),
            'Binary payload corrupted during IPC serialization'
        );

        $this->assertEquals(0x02, $restored->getOpcode(), 'Should preserve Binary Opcode');
    }

    public function test_it_returns_null_on_corrupted_data(): void
    {
        $this->assertNull(InternalEnvelope::fromSerialized('not-a-serialized-json'));
        $this->assertNull(InternalEnvelope::fromSerialized('{"wrong":"format"}'));
    }
}

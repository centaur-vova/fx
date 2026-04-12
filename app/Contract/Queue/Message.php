<?php

declare(strict_types=1);

namespace App\Contract\Queue;

interface Message
{
    public function getId(): string;

    public function getReceiptId(): string;

    public function getData(): mixed;

    public function getSubject(): string;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    public function getTimestamp(): int;

    public function getAttempts(): int;

    public function ack(): void;

    public function nack(?int $delay = null): void;

    public function reject(): void;
}

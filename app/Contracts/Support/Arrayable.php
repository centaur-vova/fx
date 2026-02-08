<?php

declare(strict_types=1);

namespace App\Contracts\Support;

/**
 * Interface for objects that can be converted to a raw array.
 * Used for standardized data transmission in the Fast.AF engine.
 */
interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

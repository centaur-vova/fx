<?php

declare(strict_types=1);

namespace App\Contract\Support;

interface Identifiable
{
    /**
     * Returns unique identifier as string
     *
     * @return non-empty-string
     */
    public function getId(): string;
}

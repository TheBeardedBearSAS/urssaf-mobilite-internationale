<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Port\Urssaf;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\BinaryStream;

/**
 * Hand-written {@see BinaryStream} double over a string held in memory.
 */
final class InMemoryBinaryStream implements BinaryStream
{
    private int $position = 0;

    private bool $closed = false;

    public function __construct(private readonly string $content) {}

    public function read(int $length): string
    {
        $chunk = substr($this->content, $this->position, $length);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->position >= \strlen($this->content);
    }

    public function copyTo(mixed $resource): int
    {
        $remaining = substr($this->content, $this->position);
        $this->position = \strlen($this->content);

        return (int) fwrite($resource, $remaining);
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @return array{content: string}
     */
    public function __debugInfo(): array
    {
        return ['content' => '***'];
    }
}

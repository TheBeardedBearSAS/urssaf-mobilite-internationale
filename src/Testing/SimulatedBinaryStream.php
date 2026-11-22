<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Testing;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\BinaryStream;

/**
 * In-memory {@see BinaryStream} of the simulated certificate. The content is hidden in debug output (ADR 0005).
 */
final class SimulatedBinaryStream implements BinaryStream
{
    private int $position = 0;

    private bool $closed = false;

    public function __construct(private readonly string $content) {}

    public function read(int $length): string
    {
        if ($this->closed) {
            return '';
        }
        $chunk = substr($this->content, $this->position, $length);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->closed || $this->position >= \strlen($this->content);
    }

    public function copyTo(mixed $resource): int
    {
        if ($this->closed) {
            return 0;
        }
        $remaining = substr($this->content, $this->position);
        $this->position = \strlen($this->content);
        fwrite($resource, $remaining);

        return \strlen($remaining);
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * @return array{content: string}
     */
    public function __debugInfo(): array
    {
        return ['content' => '***'];
    }
}

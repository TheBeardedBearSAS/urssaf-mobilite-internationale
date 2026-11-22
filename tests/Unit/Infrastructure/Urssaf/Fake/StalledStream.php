<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake;

use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A readable stream that never reaches its end and never has data (a stalled, non-blocking source): counts its reads.
 *
 * @mago-expect lint:too-many-methods StreamInterface imposes fifteen methods.
 */
final class StalledStream implements StreamInterface
{
    private int $reads = 0;

    public function reads(): int
    {
        return $this->reads;
    }

    #[Override]
    public function __toString(): string
    {
        return '';
    }

    #[Override]
    public function close(): void {}

    #[Override]
    public function detach(): null
    {
        return null;
    }

    #[Override]
    public function getSize(): ?int
    {
        return null;
    }

    #[Override]
    public function tell(): int
    {
        return 0;
    }

    #[Override]
    public function eof(): bool
    {
        return false;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Not seekable.');
    }

    #[Override]
    public function rewind(): void
    {
        throw new RuntimeException('Not seekable.');
    }

    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[Override]
    public function write(string $string): int
    {
        throw new RuntimeException('Not writable.');
    }

    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[Override]
    public function read(int $length): string
    {
        ++$this->reads;

        if ($this->reads > 3) {
            throw new RuntimeException('The copy keeps polling a stalled stream.');
        }

        return '';
    }

    #[Override]
    public function getContents(): string
    {
        return '';
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}

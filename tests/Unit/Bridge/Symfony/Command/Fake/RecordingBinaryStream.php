<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Command\Fake;

use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\BinaryStream;

/**
 * A {@see BinaryStream} double that keeps the target it was asked to copy to (to check that it is closed) and that
 * can fail halfway through the copy.
 */
final class RecordingBinaryStream implements BinaryStream
{
    private bool $closed = false;

    /** @var resource|null */
    private mixed $target = null;

    public function __construct(private readonly string $content, private readonly bool $failsAfterWriting = false) {}

    public function read(int $length): string
    {
        return '';
    }

    public function eof(): bool
    {
        return true;
    }

    public function copyTo(mixed $resource): int
    {
        $this->target = $resource;
        $written = (int) fwrite($resource, $this->content);
        if ($this->failsAfterWriting) {
            throw new RuntimeException('The download was interrupted.');
        }

        return $written;
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
     * True once the target of the copy was closed by its owner (a closed resource is no longer a "stream").
     */
    public function targetIsClosed(): bool
    {
        return $this->target !== null && !\is_resource($this->target);
    }
}

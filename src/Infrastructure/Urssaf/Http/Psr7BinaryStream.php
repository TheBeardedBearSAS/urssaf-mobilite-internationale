<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\BinaryStream;

/**
 * Application {@see BinaryStream} over the PSR-7 body of a download: read in chunks, never buffered whole nor decoded
 * (ADR 0005).
 *
 * @internal
 */
final readonly class Psr7BinaryStream implements BinaryStream
{
    private const CHUNK_SIZE = 65_536;

    public function __construct(private StreamInterface $body, private StreamFactoryInterface $streamFactory) {}

    /**
     * The certificate is personal data: the stream is not dumped (ADR 0006).
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['body' => '***'];
    }

    public function read(int $length): string
    {
        return $this->body->read($length);
    }

    public function eof(): bool
    {
        return $this->body->eof();
    }

    public function copyTo(mixed $resource): int
    {
        $target = $this->streamFactory->createStreamFromResource($resource);
        $copied = 0;

        try {
            while (!$this->body->eof()) {
                $chunk = $this->body->read(self::CHUNK_SIZE);
                // A stream that is not at its end but has nothing to give (non-blocking source) must not spin forever.
                if ($chunk === '') {
                    break;
                }
                $copied += $target->write($chunk);
            }
        } finally {
            $target->detach();
        }

        return $copied;
    }

    public function close(): void
    {
        $this->body->close();
    }
}

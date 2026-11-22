<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\Psr7BinaryStream;

final class Psr7BinaryStreamTest extends TestCase
{
    public function testItReadsTheBodyInChunksUntilTheEnd(): void
    {
        $stream = new Psr7BinaryStream(Stream::create('%PDF-1.7 abcdef'), new Psr17Factory());

        static::assertFalse($stream->eof());
        static::assertSame('%PDF-', $stream->read(5));
        static::assertSame('1.7 abcdef', $stream->read(100));
        static::assertSame('', $stream->read(1));
        static::assertTrue($stream->eof());
    }

    public function testItCopiesTheRemainingContentInBoundedChunks(): void
    {
        $content = str_repeat('0123456789abcdef', 20_000);
        $stream = new Psr7BinaryStream(Stream::create($content), new Psr17Factory());
        $stream->read(16);
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        $copied = $stream->copyTo($target);

        static::assertSame(\strlen($content) - 16, $copied);
        rewind($target);
        static::assertSame(substr($content, 16), stream_get_contents($target));
        static::assertTrue($stream->eof());
        fclose($target);
    }

    public function testCopyingAnEmptyBodyCopiesNothingAndKeepsTheTargetOpen(): void
    {
        $stream = new Psr7BinaryStream(Stream::create(''), new Psr17Factory());
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        static::assertSame(0, $stream->copyTo($target));
        static::assertSame(0, fwrite($target, ''));
        fclose($target);
    }

    public function testTheTargetResourceIsNotClosedWhenTheCopyFails(): void
    {
        $stream = new Psr7BinaryStream(Stream::create('abc'), new Psr17Factory());
        $target = fopen(__FILE__, 'rb');
        static::assertIsResource($target);

        $failed = false;
        try {
            $stream->copyTo($target);
        } catch (RuntimeException) {
            $failed = true;
        }

        static::assertTrue($failed, 'A read-only target cannot be written.');
        static::assertSame('stream', get_resource_type($target));
        fclose($target);
    }

    public function testTheTargetResourceStaysUsableAfterTheCopy(): void
    {
        $stream = new Psr7BinaryStream(Stream::create('abc'), new Psr17Factory());
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        $stream->copyTo($target);

        static::assertSame(3, fwrite($target, 'xyz'));
        fclose($target);
    }

    public function testItClosesTheBody(): void
    {
        $body = Stream::create('abc');
        $stream = new Psr7BinaryStream($body, new Psr17Factory());

        $stream->close();

        static::assertNull($body->getSize());
    }
}

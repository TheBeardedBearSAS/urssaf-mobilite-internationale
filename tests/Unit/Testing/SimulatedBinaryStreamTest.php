<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedBinaryStream;

#[CoversClass(SimulatedBinaryStream::class)]
final class SimulatedBinaryStreamTest extends TestCase
{
    public function testItReadsByChunksUntilTheEnd(): void
    {
        $stream = new SimulatedBinaryStream('abcde');

        static::assertFalse($stream->eof());
        static::assertSame('ab', $stream->read(2));
        static::assertSame('cd', $stream->read(2));
        static::assertFalse($stream->eof());
        static::assertSame('e', $stream->read(2));
        static::assertTrue($stream->eof());
        static::assertSame('', $stream->read(2));
    }

    public function testAnEmptyStreamIsAtItsEnd(): void
    {
        static::assertTrue((new SimulatedBinaryStream(''))->eof());
    }

    public function testItCopiesTheRemainingContent(): void
    {
        $stream = new SimulatedBinaryStream('abcde');
        $stream->read(2);
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        static::assertSame(3, $stream->copyTo($target));
        static::assertSame(0, $stream->copyTo($target));

        rewind($target);
        static::assertSame('cde', stream_get_contents($target));
        static::assertTrue($stream->eof());
        fclose($target);
    }

    public function testClosingEndsTheStream(): void
    {
        $stream = new SimulatedBinaryStream('abc');
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        $stream->close();

        static::assertTrue($stream->eof());
        static::assertSame('', $stream->read(10));
        static::assertSame(0, $stream->copyTo($target));
        fclose($target);
    }

    public function testTheContentIsHiddenInDebugOutput(): void
    {
        $stream = new SimulatedBinaryStream('secret certificate');

        static::assertSame(['content' => '***'], $stream->__debugInfo());
        static::assertStringNotContainsString('secret', print_r($stream, true));
    }
}

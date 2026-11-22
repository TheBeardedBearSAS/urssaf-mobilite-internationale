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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;

final class DownloadedFileTest extends TestCase
{
    public function testAccessors(): void
    {
        $stream = new InMemoryBinaryStream('%PDF-synthetic');

        $file = new DownloadedFile($stream, 'application/pdf', 'attachment; filename="certificat.pdf"');

        static::assertSame($stream, $file->stream());
        static::assertSame('application/pdf', $file->contentType());
        static::assertSame('attachment; filename="certificat.pdf"', $file->contentDisposition());
    }

    public function testHeadersAreOptional(): void
    {
        $file = new DownloadedFile(new InMemoryBinaryStream(''), null, null);

        static::assertNull($file->contentType());
        static::assertNull($file->contentDisposition());
        static::assertSame(['stream' => '***', 'contentType' => null, 'contentDisposition' => null], $file->__debugInfo());
    }

    public function testDebugOutputHidesTheContentAndTheFileName(): void
    {
        $file = new DownloadedFile(new InMemoryBinaryStream('%PDF-synthetic'), 'application/pdf', 'attachment; filename="worker.pdf"');

        static::assertSame(
            ['stream' => '***', 'contentType' => 'application/pdf', 'contentDisposition' => '***'],
            $file->__debugInfo(),
        );
        $dump = print_r($file, true);
        static::assertStringNotContainsString('%PDF', $dump);
        static::assertStringNotContainsString('worker.pdf', $dump);
    }

    public function testTheStreamIsReadWithoutBuffering(): void
    {
        $stream = new InMemoryBinaryStream('%PDF-synthetic');
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);

        static::assertSame('%PDF', $stream->read(4));
        static::assertFalse($stream->eof());
        static::assertSame(10, $stream->copyTo($target));
        static::assertTrue($stream->eof());
        static::assertSame('', $stream->read(4));
        rewind($target);
        static::assertSame('-synthetic', stream_get_contents($target));
        $stream->close();
        static::assertTrue($stream->isClosed());
        fclose($target);
    }
}

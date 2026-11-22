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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

/**
 * The certificate is a binary body: streamed, never decoded, never logged (ADR 0005).
 */
final class JsonApiClientDownloadTest extends TestCase
{
    private const BINARY = "%PDF-1.7\n\x00\xFF\xFE binary \"not json\" {]\n%%EOF";

    private function download(Stack $stack): ?DownloadedFile
    {
        return $stack->jsonClient()->download(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            Calls::request(Operation::GetCertificate),
        );
    }

    public function testA200IsStreamedWithItsHeadersAndNeverDecoded(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, self::BINARY, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificate.pdf"',
        ]);

        $file = $this->download($stack);

        static::assertInstanceOf(DownloadedFile::class, $file);
        static::assertSame('application/pdf', $file->contentType());
        static::assertSame('attachment; filename="certificate.pdf"', $file->contentDisposition());
        $target = fopen('php://memory', 'w+b');
        static::assertIsResource($target);
        static::assertSame(\strlen(self::BINARY), $file->stream()->copyTo($target));
        rewind($target);
        static::assertSame(self::BINARY, stream_get_contents($target));
        fclose($target);
    }

    public function testTheContentCanBeReadInChunks(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, self::BINARY, ['Content-Type' => 'application/pdf']);

        $file = $this->download($stack);

        static::assertInstanceOf(DownloadedFile::class, $file);
        static::assertSame('%PDF-', $file->stream()->read(5));
        static::assertFalse($file->stream()->eof());
    }

    public function testTheHeadersAreNullWhenTheyAreNotAnswered(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, self::BINARY);

        $file = $this->download($stack);

        static::assertInstanceOf(DownloadedFile::class, $file);
        static::assertNull($file->contentType());
        static::assertNull($file->contentDisposition());
    }

    public function testA204HasNoFileAndItsBodyIsNotRead(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(204, 'ignored');

        static::assertNull($this->download($stack));
    }

    public function testTheContentIsNeverLoggedNorThrownInAnException(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, 'CERTIFICATE-CONTENT-OF-A-WORKER', ['Content-Disposition' => 'attachment; filename="worker-name.pdf"']);

        $this->download($stack);

        static::assertStringNotContainsString('CERTIFICATE-CONTENT-OF-A-WORKER', $stack->logger->dump());
        static::assertStringNotContainsString('worker-name', $stack->logger->dump());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CertificateFile;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\ScratchDirectory;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Command\Fake\RecordingBinaryStream;

#[CoversClass(CertificateFile::class)]
final class CertificateFileTest extends TestCase
{
    private const CONTENT = "fake certificate \x00\x01\x02";

    private ScratchDirectory $scratch;

    protected function setUp(): void
    {
        $this->scratch = ScratchDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->scratch->remove();
    }

    public function testTheStreamIsCopiedToANewFileWithTheModeOwnerOnly(): void
    {
        $path = $this->scratch->path('certificate.pdf');
        $stream = new RecordingBinaryStream(self::CONTENT);

        $written = CertificateFile::write($stream, $path, false);

        static::assertSame(\strlen(self::CONTENT), $written);
        static::assertSame(self::CONTENT, file_get_contents($path));
        static::assertSame(0o600, fileperms($path) & 0o777);
        static::assertTrue($stream->isClosed());
        static::assertTrue($stream->targetIsClosed());
    }

    public function testTheProcessUmaskIsRestored(): void
    {
        $previous = umask(0o022);
        try {
            CertificateFile::write(new RecordingBinaryStream(self::CONTENT), $this->scratch->path('certificate.pdf'), false);
            $after = umask($previous);
        } finally {
            umask($previous);
        }

        static::assertSame(0o022, $after);
    }

    public function testTheProcessUmaskIsRestoredWhenTheFileCannotBeCreated(): void
    {
        $previous = umask(0o022);
        try {
            try {
                CertificateFile::write(new RecordingBinaryStream(self::CONTENT), $this->scratch->path('missing/certificate.pdf'), false);
            } catch (InvalidOptionException $refused) {
                static::assertStringContainsString('cannot be created', $refused->getMessage());
            }
            $after = umask($previous);
        } finally {
            umask($previous);
        }

        static::assertSame(0o022, $after);
    }

    public function testAnExistingFileIsKeptAndTheStreamClosedWhenNotReplacing(): void
    {
        $path = $this->scratch->path('certificate.pdf');
        file_put_contents($path, 'precious');
        $stream = new RecordingBinaryStream(self::CONTENT);

        try {
            CertificateFile::write($stream, $path, false);
            static::fail('An existing file must not be replaced without --force.');
        } catch (InvalidOptionException $refused) {
            static::assertSame('The output file already exists: choose another path or use --force to replace it.', $refused->getMessage());
        }

        static::assertSame('precious', file_get_contents($path));
        static::assertTrue($stream->isClosed());
    }

    public function testAnExistingFileIsReplacedByANewOneWithTheModeOwnerOnly(): void
    {
        $path = $this->scratch->path('certificate.pdf');
        file_put_contents($path, 'old content that is longer than the new one, so a truncation would show');
        chmod($path, 0o666);

        CertificateFile::write(new RecordingBinaryStream('new'), $path, true);

        static::assertSame('new', file_get_contents($path));
        static::assertSame(0o600, fileperms($path) & 0o777);
    }

    public function testAFileThatCannotBeRemovedIsAnError(): void
    {
        $stream = new RecordingBinaryStream(self::CONTENT);

        try {
            CertificateFile::write($stream, $this->scratch->path(''), true);
            static::fail('A directory cannot be replaced by a file.');
        } catch (InvalidOptionException $refused) {
            static::assertSame('The existing output file cannot be replaced.', $refused->getMessage());
        }

        static::assertTrue($stream->isClosed());
    }

    public function testAFileThatCannotBeCreatedIsAnError(): void
    {
        $stream = new RecordingBinaryStream(self::CONTENT);

        try {
            CertificateFile::write($stream, $this->scratch->path('missing/certificate.pdf'), false);
            static::fail('The directory does not exist.');
        } catch (InvalidOptionException $refused) {
            static::assertSame('The output file cannot be created: check that its directory exists and is writable.', $refused->getMessage());
        }

        static::assertTrue($stream->isClosed());
    }

    public function testAnInterruptedCopyLeavesNoPartialFileAndClosesEverything(): void
    {
        $path = $this->scratch->path('certificate.pdf');
        $stream = new RecordingBinaryStream(self::CONTENT, true);

        try {
            CertificateFile::write($stream, $path, false);
            static::fail('The failure of the stream must reach the caller.');
        } catch (RuntimeException $failure) {
            static::assertSame('The download was interrupted.', $failure->getMessage());
        }

        static::assertFileDoesNotExist($path);
        static::assertTrue($stream->isClosed());
        static::assertTrue($stream->targetIsClosed());
    }
}

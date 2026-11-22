<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\ScratchDirectory;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;

/**
 * "urssaf:mi:certificate": the PDF goes to a file created with the mode 0600, never to the terminal. Exit codes: 0 written,
 * 3 not available yet, 1 refused or unknown dossier and every failure, 2 invalid usage.
 *
 * @mago-expect lint:too-many-methods One test per outcome of the command.
 */
final class CertificateCommandTest extends TestCase
{
    private ScratchDirectory $scratch;

    protected function setUp(): void
    {
        $this->scratch = ScratchDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->scratch->remove();
    }

    public function testTheCommandIsDefined(): void
    {
        $harness = Harness::boot();
        $command = (new Application($harness->kernel))->find('urssaf:mi:certificate');

        static::assertSame('Downloads the certificate of a posting dossier into a file', $command->getDescription());
        static::assertTrue($command->getDefinition()->getArgument('dossierId')->isRequired());
        static::assertTrue($command->getDefinition()->getArgument('output-file')->isRequired());
        static::assertFalse($command->getDefinition()->getOption('force')->acceptValue());
    }

    public function testTheCertificateIsWrittenWithTheModeOwnerOnlyAndNeverPrinted(): void
    {
        $harness = Harness::boot();
        $path = $this->scratch->path('certificate.pdf');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(0, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path]));

        static::assertSame(HttpScript::CERTIFICATE_BYTES, file_get_contents($path));
        static::assertSame(0o600, fileperms($path) & 0o777);
        static::assertSame(
            \sprintf("Certificate written (%d bytes).\n", \strlen(HttpScript::CERTIFICATE_BYTES)),
            $command->getDisplay(),
        );
        static::assertStringNotContainsString('PDF', $command->getDisplay());
        static::assertSame('/tiersdecl/v1/mobilite/certificat/' . Calls::DOSSIER_ID, self::lastPath($harness));
    }

    public function testTheModeDoesNotDependOnTheUmaskOfTheProcess(): void
    {
        $harness = Harness::boot();
        $path = $this->scratch->path('certificate.pdf');
        $previous = umask(0);
        try {
            $exit = $harness->command('urssaf:mi:certificate')->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path]);
            $umaskAfter = umask($previous);
        } finally {
            umask($previous);
        }

        static::assertSame(0, $exit);
        static::assertSame(0o600, fileperms($path) & 0o777);
        static::assertSame(0, $umaskAfter, 'The umask of the process is restored.');
    }

    public function testAnExistingFileIsKeptUnlessForced(): void
    {
        $harness = Harness::boot();
        $path = $this->scratch->path('certificate.pdf');
        file_put_contents($path, 'precious');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(2, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path]));

        static::assertStringContainsString('The output file already exists: choose another path or use --force to replace it.', $command->getDisplay());
        static::assertSame('precious', file_get_contents($path));
    }

    public function testForceReplacesAnExistingFileAndTightensItsMode(): void
    {
        $harness = Harness::boot();
        $path = $this->scratch->path('certificate.pdf');
        file_put_contents($path, 'old content');
        chmod($path, 0o644);
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(0, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path, '--force' => true]));

        static::assertSame(HttpScript::CERTIFICATE_BYTES, file_get_contents($path));
        static::assertSame(0o600, fileperms($path) & 0o777);
    }

    public function testForceDoesNotFollowASymbolicLink(): void
    {
        $harness = Harness::boot();
        $target = $this->scratch->path('target.txt');
        $link = $this->scratch->path('link.pdf');
        file_put_contents($target, 'untouched');
        symlink($target, $link);

        static::assertSame(0, $harness->command('urssaf:mi:certificate')->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $link, '--force' => true]));

        static::assertSame('untouched', file_get_contents($target));
        static::assertFalse(is_link($link));
        static::assertSame(HttpScript::CERTIFICATE_BYTES, file_get_contents($link));
    }

    public function testADanglingSymbolicLinkIsAnExistingFile(): void
    {
        $harness = Harness::boot();
        $link = $this->scratch->path('link.pdf');
        symlink($this->scratch->path('missing.txt'), $link);
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(2, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $link]));

        static::assertFileDoesNotExist($this->scratch->path('missing.txt'));
    }

    public function testADirectoryCannotBeReplaced(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(2, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path(''), '--force' => true]));

        static::assertStringContainsString('The existing output file cannot be replaced.', $command->getDisplay());
    }

    public function testAMissingDirectoryIsAnInvalidUsage(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(2, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path('missing/certificate.pdf')]));

        static::assertStringContainsString('The output file cannot be created: check that its directory exists and is writable.', $command->getDisplay());
    }

    /**
     * @return iterable<string, array{int, string}> status of the download, content type
     */
    public static function notReadyYet(): iterable
    {
        yield 'in progress (204)' => [204, 'application/json'];
        yield 'bad request, in progress by default (400)' => [400, 'application/json'];
    }

    #[DataProvider('notReadyYet')]
    public function testACertificateNotAvailableYetExitsWithThreeAndWritesNothing(int $status, string $contentType): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, $status, '', ['Content-Type' => $contentType]);
        $path = $this->scratch->path('certificate.pdf');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(3, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path]));

        static::assertStringContainsString(\sprintf('The certificate is not available yet (HTTP %d): try again later.', $status), $command->getDisplay());
        static::assertFileDoesNotExist($path);
    }

    public function testTheUrssafDetailOfAnUnavailableCertificateIsPrintedOnRequest(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 400, '{"code":"E400","message":"not yet","description":"instruction"}');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(3, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path('c.pdf'), '--show-urssaf-errors' => true]));

        static::assertContains(['E400', 'not yet', 'instruction'], Output::rows($command->getDisplay()));
    }

    public function testAnUnknownOrRefusedDossierIsAFailureAndWritesNothing(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 404, '{"code":"E404","message":"refused","description":"details"}');
        $path = $this->scratch->path('certificate.pdf');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(1, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $path]));

        static::assertStringContainsString('The dossier is unknown or its file was refused (HTTP 404).', $command->getDisplay());
        static::assertStringNotContainsString('E404', $command->getDisplay());
        static::assertFileDoesNotExist($path);
    }

    public function testTheUrssafDetailOfARefusedDossierIsPrintedOnRequest(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 404, '{"code":"E404","message":"refused","description":"details"}');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(1, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path('c.pdf'), '--show-urssaf-errors' => true]));

        static::assertContains(['E404', 'refused', 'details'], Output::rows($command->getDisplay()));
    }

    public function testABadRequestIsAFailureWhenConfiguredSo(): void
    {
        $harness = Harness::boot(['certificate' => ['bad_request_as_in_progress' => false]]);
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 400, '');
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(1, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path('c.pdf')]));

        static::assertFileDoesNotExist($this->scratch->path('c.pdf'));
    }

    public function testAnUnavailableApiIsAFailureAndWritesNothing(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_CERTIFICATE);
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(1, $command->execute(['dossierId' => Calls::DOSSIER_ID, 'output-file' => $this->scratch->path('c.pdf')]));

        static::assertFileDoesNotExist($this->scratch->path('c.pdf'));
    }

    public function testAnInvalidDossierIdIsAnInvalidUsage(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:certificate');

        static::assertSame(2, $command->execute(['dossierId' => 'nope', 'output-file' => $this->scratch->path('c.pdf')]));

        static::assertStringContainsString('The dossier identifier does not match the expected format (UUID).', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    private static function lastPath(Harness $harness): string
    {
        $requests = $harness->client->getRequests();
        $last = end($requests);
        static::assertNotFalse($last);

        return $last->getUri()->getPath();
    }
}

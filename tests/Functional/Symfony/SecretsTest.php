<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsNotSerializable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * The client secret only ever travels as an environment variable reference, and is masked wherever the credentials
 * are dumped.
 */
final class SecretsTest extends TestCase
{
    public function testTheContainerDumpOnlyShowsTheEnvironmentReferenceOfTheSecret(): void
    {
        $harness = Harness::boot();

        // Every file of the compiled container of the test application.
        $dump = '';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($harness->kernel->getCacheDir(), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            static::assertInstanceOf(SplFileInfo::class, $file);
            $dump .= (string) file_get_contents($file->getPathname());
        }

        static::assertStringContainsString('URSSAF_TEST_SECRET', $dump);
        static::assertStringNotContainsString(TestKernel::SECRET, $dump);
    }

    public function testTheCredentialsMaskTheirSecretWhenDumped(): void
    {
        $provider = Harness::boot()->privateService(CredentialsProvider::class, CredentialsProvider::class);
        $credentials = $provider->forTenant(TenantId::fromString('tenant-1'));

        static::assertSame(TestKernel::SECRET, $credentials->clientSecret());
        ob_start();
        // @mago-expect lint:no-debug-symbols The dump of the credentials is exactly what is under test.
        var_dump($provider, $credentials);
        $dump = (string) ob_get_clean();
        static::assertStringContainsString('client-1', $dump);
        static::assertStringNotContainsString(TestKernel::SECRET, $dump);
        static::assertStringNotContainsString(TestKernel::SECRET, print_r($credentials, true));

        $this->expectException(CredentialsNotSerializable::class);

        serialize($credentials);
    }

    public function testTheSecretNeverReachesTheLogs(): void
    {
        $harness = Harness::boot();
        $harness->command('urssaf:mi:enrolment:list')->execute([]);

        $logger = $harness->privateService('logger', CapturingLogger::class);
        static::assertNotSame([], $logger->records());
        static::assertStringNotContainsString(TestKernel::SECRET, $logger->dump());
        static::assertStringNotContainsString('fictitious-access-token', $logger->dump());
    }
}

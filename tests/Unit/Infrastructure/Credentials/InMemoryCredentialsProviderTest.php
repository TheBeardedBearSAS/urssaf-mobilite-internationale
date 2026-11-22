<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Credentials;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\CredentialsNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;

final class InMemoryCredentialsProviderTest extends TestCase
{
    public function testReturnsTheCredentialsOfTheTenant(): void
    {
        $credentials = Credentials::of('client-a', 'secret-a');
        $provider = new InMemoryCredentialsProvider([
            'tenant-a' => $credentials,
            'tenant-b' => Credentials::of('client-b', 'secret-b'),
        ]);

        static::assertSame($credentials, $provider->forTenant(TenantId::fromString('tenant-a')));
    }

    public function testUnknownTenantIsCredentialsNotFound(): void
    {
        $provider = new InMemoryCredentialsProvider([
            'tenant-a' => Credentials::of('client-a', 'secret-a'),
        ]);

        $this->expectExceptionObject(CredentialsNotFound::forTenant(TenantId::fromString('tenant-z')));

        $provider->forTenant(TenantId::fromString('tenant-z'));
    }

    public function testNoCredentialsAtAllIsCredentialsNotFound(): void
    {
        $provider = new InMemoryCredentialsProvider([]);

        $this->expectExceptionObject(CredentialsNotFound::forTenant(TenantId::fromString('tenant-a')));

        $provider->forTenant(TenantId::fromString('tenant-a'));
    }

    public function testUnknownTenantErrorCarriesTheDocumentedCode(): void
    {
        $provider = new InMemoryCredentialsProvider([]);

        try {
            $provider->forTenant(TenantId::fromString('tenant-a'));
            static::fail('The tenant has no credentials.');
        } catch (CredentialsNotFound $exception) {
            static::assertSame('credentials.not_found', $exception->violationCode());
        }
    }
}

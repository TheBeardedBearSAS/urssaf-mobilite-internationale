<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\CredentialsNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Exceptions;

final class CredentialsNotFoundTest extends TestCase
{
    public function testItCarriesTheDocumentedViolationCodeAndNamesTheTenantOnly(): void
    {
        $exception = CredentialsNotFound::forTenant(TenantId::fromString('tenant-a'));

        static::assertTrue(Exceptions::isUrssafException($exception));
        static::assertSame('credentials.not_found', $exception->violationCode());
        static::assertSame('No credentials are configured for tenant "tenant-a".', $exception->getMessage());
        static::assertSame([], $exception->params());
    }
}

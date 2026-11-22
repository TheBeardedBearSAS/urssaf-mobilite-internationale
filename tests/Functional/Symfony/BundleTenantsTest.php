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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;

final class BundleTenantsTest extends TestCase
{
    public function testTheTenantsAreTheKeysOfTheCredentialsByDefault(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]]);

        static::assertSame(['tenant-1', 'tenant-2'], self::tenants($harness));
    }

    public function testNumericTenantIdsAreStrings(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['123' => ['client_id' => 'client-3', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]]);

        static::assertSame(['tenant-1', '123'], self::tenants($harness));
    }

    public function testTheTenantsCanBeListedExplicitly(): void
    {
        static::assertSame(['tenant-9'], self::tenants(Harness::boot(['tenants' => ['tenant-9']])));
    }

    public function testThereIsNoTenantByDefaultWithACredentialsProvider(): void
    {
        $harness = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials_provider']);

        static::assertSame([], self::tenants($harness));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function tenants(Harness $harness): array
    {
        $tenants = $harness->kernel->getContainer()->getParameter(UrssafMobiliteInternationaleBundle::PARAMETER_TENANTS);
        static::assertIsArray($tenants);

        return $tenants;
    }
}

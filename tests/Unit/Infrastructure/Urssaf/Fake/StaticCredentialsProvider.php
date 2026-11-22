<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

final class StaticCredentialsProvider implements CredentialsProvider
{
    public const CLIENT_ID = 'fictitious-client-id';
    public const SHARED_VALUE = 'fictitious-client-secret-value';

    public function forTenant(TenantId $tenantId): Credentials
    {
        return Credentials::of(self::CLIENT_ID, self::SHARED_VALUE);
    }
}

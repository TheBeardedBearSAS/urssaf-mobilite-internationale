<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Exception;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\DomainException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * No OAuth2 credentials are configured for the tenant (ADR 0007). Thrown before any call: nothing was sent to URSSAF.
 * The violation code is "credentials.not_found"; the message names the tenant identifier only, never a secret.
 */
final class CredentialsNotFound extends DomainException
{
    public const CODE = 'credentials.not_found';

    public static function forTenant(TenantId $tenantId): self
    {
        return new self(\sprintf('No credentials are configured for tenant "%s".', $tenantId->value()), self::CODE, []);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * The only source of secrets (ADR 0007): a tenant is one set of OAuth2 credentials of one issuer.
 */
interface CredentialsProvider
{
    /**
     * @throws UrssafException when the tenant has no credentials (violation code "credentials.not_found")
     */
    public function forTenant(TenantId $tenantId): Credentials;
}

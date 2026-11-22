<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\CredentialsNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Credentials provider backed by a map of tenant identifier to credentials (configuration or tests).
 */
final readonly class InMemoryCredentialsProvider implements CredentialsProvider
{
    /**
     * @param array<string, Credentials> $credentialsByTenant keyed by tenant identifier
     */
    public function __construct(private array $credentialsByTenant) {}

    /**
     * @throws CredentialsNotFound "credentials.not_found"
     */
    public function forTenant(TenantId $tenantId): Credentials
    {
        return $this->credentialsByTenant[$tenantId->value()] ?? throw CredentialsNotFound::forTenant($tenantId);
    }
}

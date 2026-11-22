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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Opt-in client-side throttling, per tenant (ADR 0000 row 14), disabled by default: the published
 * quota ({@see Operation::quotaPerMinute()}) is global to the API and informational. An HTTP 429 stays an
 * {@see Urssaf\UrssafUnavailable::quotaExceeded()}.
 */
interface RateLimiter
{
    /**
     * Reserves one call slot, blocking until one is available or throwing when the implementation is non-blocking.
     *
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void;
}

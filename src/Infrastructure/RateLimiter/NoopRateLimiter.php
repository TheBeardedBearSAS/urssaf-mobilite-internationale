<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\RateLimiter;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Rate limiter that never throttles (the remote quota then applies alone, as HTTP 429).
 */
final class NoopRateLimiter implements RateLimiter
{
    public function acquire(TenantId $tenantId, Operation $operation): void {}
}

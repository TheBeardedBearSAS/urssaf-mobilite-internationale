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

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

final class RecordingRateLimiter implements RateLimiter
{
    /** @var list<string> */
    private array $acquired = [];

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $this->acquired[] = $tenantId->value() . ':' . $operation->value;
    }

    /**
     * @return list<string>
     */
    public function acquired(): array
    {
        return $this->acquired;
    }
}

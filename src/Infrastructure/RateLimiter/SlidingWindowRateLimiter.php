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

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Non-blocking in-memory rate limiter: at most {@see Operation::quotaPerMinute()} calls per tenant and operation in any
 * sliding 60-second window. Call instants are kept at second precision; when the quota is reached, the retry delay
 * is the time left until the oldest call leaves the window.
 */
final class SlidingWindowRateLimiter implements RateLimiter
{
    private const WINDOW_SECONDS = 60;

    /** @var array<array-key, array<array-key, array<int, int>>> call instants (UNIX timestamps) per tenant, then per operation */
    private array $calls = [];

    public function __construct(private readonly Clock $clock) {}

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - self::WINDOW_SECONDS;
        $recent = array_filter(
            $this->calls[$tenantId->value()][$operation->value] ?? [],
            static fn(int $instant): bool => $instant > $windowStart,
        );

        if (\count($recent) >= $operation->quotaPerMinute()) {
            throw RateLimitExceeded::forOperation($operation, min($now, ...$recent) + self::WINDOW_SECONDS - $now);
        }

        $recent[] = $now;
        $this->calls[$tenantId->value()][$operation->value] = $recent;
    }
}

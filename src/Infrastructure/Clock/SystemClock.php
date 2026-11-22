<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;

/**
 * Clock port backed by any PSR-20 clock (the framework's system clock in production).
 */
final readonly class SystemClock implements Clock
{
    public function __construct(private ClockInterface $clock) {}

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}

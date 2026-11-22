<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UtcSystemClock;

final class UtcSystemClockTest extends TestCase
{
    public function testItReturnsTheCurrentTimeInUtc(): void
    {
        $before = new DateTimeImmutable('now');
        $now = (new UtcSystemClock())->now();
        $after = new DateTimeImmutable('now');

        static::assertSame('UTC', $now->getTimezone()->getName());
        static::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }
}

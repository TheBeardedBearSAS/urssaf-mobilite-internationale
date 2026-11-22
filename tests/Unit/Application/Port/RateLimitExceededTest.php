<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

final class RateLimitExceededTest extends TestCase
{
    public function testAccessors(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::SubmitPosting, 12);

        static::assertContains(RetryableException::class, class_implements($exception));
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertSame(Operation::SubmitPosting, $exception->operation());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame('Local rate limit reached for URSSAF operation post-declaration-grands-comptes; retry in 12 second(s).', $exception->getMessage());
    }

    public function testNegativeDelaysAreClamped(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::GetCertificate, -3);

        static::assertSame(0, $exception->retryAfterSeconds());
        static::assertStringEndsWith('retry in 0 second(s).', $exception->getMessage());
        static::assertSame(0, RateLimitExceeded::forOperation(Operation::GetCertificate, 0)->retryAfterSeconds());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterTest extends TestCase
{
    private const START = '2026-01-01T10:00:00+00:00';

    public function testAllowsCallsUpToTheOfficialQuota(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < Operation::RequestEnrolment->quotaPerMinute(); ++$i) {
            static::assertNull(self::retryAfterOrNull($limiter, $tenant, Operation::RequestEnrolment));
        }
    }

    public function testRejectsTheCallAboveTheQuota(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RequestEnrolment);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::RequestEnrolment, 60));

        $limiter->acquire($tenant, Operation::RequestEnrolment);
    }

    public function testRetryDelayIsTheTimeUntilTheOldestCallLeavesTheWindow(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::RequestEnrolment);
        $clock->modify('+10 seconds');
        for ($i = 1; $i < Operation::RequestEnrolment->quotaPerMinute(); ++$i) {
            $limiter->acquire($tenant, Operation::RequestEnrolment);
        }

        static::assertSame(50, self::retryAfterOrNull($limiter, $tenant, Operation::RequestEnrolment));
    }

    public function testExceptionNamesTheOperation(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::GetEnrolment);

        try {
            $limiter->acquire($tenant, Operation::GetEnrolment);
            static::fail('The quota should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::GetEnrolment, $exception->operation());
        }
    }

    public function testCallsLeaveTheWindowExactlySixtySecondsLater(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RequestEnrolment);

        $clock->modify('+59 seconds');
        static::assertSame(1, self::retryAfterOrNull($limiter, $tenant, Operation::RequestEnrolment));

        $clock->modify('+1 second');
        static::assertNull(self::retryAfterOrNull($limiter, $tenant, Operation::RequestEnrolment));
    }

    public function testQuotaIsCountedPerTenant(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RequestEnrolment);

        static::assertNull(self::retryAfterOrNull($limiter, TenantId::fromString('tenant-b'), Operation::RequestEnrolment));
    }

    public function testTheQuotaIsSharedByEveryOperationOfATenant(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::GetEnrolment);

        static::assertSame(60, self::retryAfterOrNull($limiter, $tenant, Operation::SubmitPosting));
    }

    public function testTheQuotaIsConsumedByAMixOfOperations(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        for ($i = 0; $i < Operation::GetEnrolment->quotaPerMinute(); ++$i) {
            $limiter->acquire($tenant, $i % 2 === 0 ? Operation::GetEnrolment : Operation::ListEnrolments);
        }

        try {
            $limiter->acquire($tenant, Operation::SubmitPosting);
            static::fail('The quota should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::SubmitPosting, $exception->operation());
        }
    }

    private static function fillQuota(
        SlidingWindowRateLimiter $limiter,
        TenantId $tenant,
        Operation $operation,
    ): void {
        for ($i = 0; $i < $operation->quotaPerMinute(); ++$i) {
            $limiter->acquire($tenant, $operation);
        }
    }

    /**
     * Returns the retry delay when the call is rejected, null when it is allowed.
     */
    private static function retryAfterOrNull(
        SlidingWindowRateLimiter $limiter,
        TenantId $tenant,
        Operation $operation,
    ): ?int {
        try {
            $limiter->acquire($tenant, $operation);
        } catch (RateLimitExceeded $exception) {
            return $exception->retryAfterSeconds();
        }

        return null;
    }
}

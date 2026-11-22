<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\DependencyInjection;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\GatewayOptionsFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

#[CoversClass(GatewayOptionsFactory::class)]
final class GatewayOptionsFactoryTest extends TestCase
{
    public function testTheClockAndTheLoggerAreGiven(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC'));
        $logger = new CapturingLogger();

        $options = GatewayOptionsFactory::create($clock, $logger, false, true);

        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
    }

    public function testWithoutALoggerNoneIsGiven(): void
    {
        $options = GatewayOptionsFactory::create(new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC')), null, false, true);

        static::assertNull($options->logger());
    }

    public function testNoRateLimiterByDefault(): void
    {
        $options = GatewayOptionsFactory::create(new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC')), null, false, true);

        static::assertNull($options->rateLimiter());
    }

    public function testTheRateLimiterEnforcesThePublishedQuotaOnTheClockOfThePackage(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC'));
        $limiter = GatewayOptionsFactory::create($clock, null, true, true)->rateLimiter();
        static::assertNotNull($limiter);
        for ($call = 0; $call < Operation::PRODUCTION_QUOTA_PER_MINUTE; ++$call) {
            $limiter->acquire(EnrolmentSamples::tenant(), Operation::ListEnrolments);
        }

        $this->expectException(RateLimitExceeded::class);

        $limiter->acquire(EnrolmentSamples::tenant(), Operation::ListEnrolments);
    }

    public function testTheCertificateBadRequestOptionIsGiven(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC'));

        static::assertTrue(GatewayOptionsFactory::create($clock, null, false, true)->certificateBadRequestAsInProgress());
        static::assertFalse(GatewayOptionsFactory::create($clock, null, false, false)->certificateBadRequestAsInProgress());
    }

    public function testEveryOptionSurvivesTheOthers(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2099-03-01 00:00:00 UTC'));
        $logger = new CapturingLogger();

        $options = GatewayOptionsFactory::create($clock, $logger, true, false);

        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
        static::assertNotNull($options->rateLimiter());
        static::assertFalse($options->certificateBadRequestAsInProgress());
    }
}

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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;

final class GatewayOptionsTest extends TestCase
{
    public function testDefaultsGiveNothingAndTreatACertificateBadRequestAsInProgress(): void
    {
        $options = GatewayOptions::defaults();

        static::assertNull($options->rateLimiter());
        static::assertNull($options->idGenerator());
        static::assertNull($options->clock());
        static::assertNull($options->logger());
        static::assertTrue($options->certificateBadRequestAsInProgress());
    }

    public function testEachWitherKeepsTheOtherSettings(): void
    {
        $limiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new SteppingClock(0);
        $logger = new CapturingLogger();

        $options = GatewayOptions::defaults()
            ->withRateLimiter($limiter)
            ->withIdGenerator($ids)
            ->withClock($clock)
            ->withLogger($logger)
            ->withCertificateBadRequestAsInProgress(false);

        static::assertSame($limiter, $options->rateLimiter());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
        static::assertFalse($options->certificateBadRequestAsInProgress());
    }

    public function testTheWithersAreOrderIndependentAndImmutable(): void
    {
        $logger = new CapturingLogger();
        $base = GatewayOptions::defaults()->withCertificateBadRequestAsInProgress(false);

        $withLogger = $base->withLogger($logger);
        $reenabled = $withLogger->withCertificateBadRequestAsInProgress(true);

        static::assertNull($base->logger());
        static::assertFalse($withLogger->certificateBadRequestAsInProgress());
        static::assertSame($logger, $reenabled->logger());
        static::assertTrue($reenabled->certificateBadRequestAsInProgress());
        static::assertTrue(GatewayOptions::defaults()->certificateBadRequestAsInProgress());
    }

    public function testEachWitherPreservesEveryOtherCollaborator(): void
    {
        $limiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new SteppingClock(0);
        $logger = new CapturingLogger();
        $full = GatewayOptions::defaults()
            ->withLogger($logger)
            ->withClock($clock)
            ->withIdGenerator($ids)
            ->withRateLimiter($limiter)
            ->withCertificateBadRequestAsInProgress(false);

        $other = new CapturingLogger();
        $replaced = $full->withLogger($other);
        static::assertSame($other, $replaced->logger());
        static::assertSame($clock, $replaced->clock());
        static::assertSame($ids, $replaced->idGenerator());
        static::assertSame($limiter, $replaced->rateLimiter());
        static::assertFalse($replaced->certificateBadRequestAsInProgress());

        $otherClock = new SteppingClock(1);
        $replaced = $full->withClock($otherClock);
        static::assertSame($otherClock, $replaced->clock());
        static::assertSame($logger, $replaced->logger());
        static::assertSame($ids, $replaced->idGenerator());
        static::assertSame($limiter, $replaced->rateLimiter());

        $otherIds = new SequenceIdGenerator();
        $replaced = $full->withIdGenerator($otherIds);
        static::assertSame($otherIds, $replaced->idGenerator());
        static::assertSame($logger, $replaced->logger());
        static::assertSame($clock, $replaced->clock());
        static::assertSame($limiter, $replaced->rateLimiter());

        $otherLimiter = new RecordingRateLimiter();
        $replaced = $full->withRateLimiter($otherLimiter);
        static::assertSame($otherLimiter, $replaced->rateLimiter());
        static::assertSame($logger, $replaced->logger());
        static::assertSame($clock, $replaced->clock());
        static::assertSame($ids, $replaced->idGenerator());

        $replaced = $full->withCertificateBadRequestAsInProgress(true);
        static::assertSame($limiter, $replaced->rateLimiter());
        static::assertSame($ids, $replaced->idGenerator());
        static::assertSame($clock, $replaced->clock());
        static::assertSame($logger, $replaced->logger());
    }
}

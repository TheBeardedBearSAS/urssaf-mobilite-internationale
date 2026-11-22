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

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\PostingValidationPolicyFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\SimulatedGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UtcSystemClock;
use ValueError;

#[CoversClass(ClockFactory::class)]
#[CoversClass(PostingValidationPolicyFactory::class)]
#[CoversClass(SimulatedGatewayFactory::class)]
final class SmallFactoriesTest extends TestCase
{
    public function testTheClockOfTheApplicationIsUsedWhenThereIsOne(): void
    {
        $clock = ClockFactory::create(new MockClock('2022-03-01 10:00:00 UTC'));

        static::assertInstanceOf(SystemClock::class, $clock);
        static::assertSame('2022-03-01T10:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }

    public function testTheUtcSystemClockIsTheFallback(): void
    {
        static::assertInstanceOf(UtcSystemClock::class, ClockFactory::create(null));
    }

    public function testTheSwaggerRulesAreTheDefaultPolicy(): void
    {
        static::assertFalse(PostingValidationPolicyFactory::create(false)->appliesPdfTeleworkRules());
    }

    public function testThePdfTeleworkRulesAreOptIn(): void
    {
        static::assertTrue(PostingValidationPolicyFactory::create(true)->appliesPdfTeleworkRules());
    }

    public function testTheSandboxGetsTheSimulator(): void
    {
        static::assertSame([], SimulatedGatewayFactory::create('sandbox')->calls());
    }

    public function testProductionIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The simulated gateway is meant for tests and cannot be used with the production environment.');

        SimulatedGatewayFactory::create('production');
    }

    public function testAnUnknownEnvironmentIsRefused(): void
    {
        $this->expectException(ValueError::class);

        SimulatedGatewayFactory::create('staging');
    }
}

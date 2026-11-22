<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;

final class TeleworkTest extends TestCase
{
    public function testWithTelework(): void
    {
        $telework = Telework::withTelework(true, Country::Italie, 2);

        static::assertTrue($telework->teleworking());
        static::assertTrue($telework->workerAgreement());
        static::assertSame(Country::Italie, $telework->country());
        static::assertSame(2, $telework->daysPerMonth());
        static::assertSame(1, Telework::withTelework(null, null, 1)->daysPerMonth());
        static::assertSame(31, Telework::withTelework(false, null, 31)->daysPerMonth());
        static::assertSame(['teleworking' => '***', 'workerAgreement' => '***', 'country' => '***', 'daysPerMonth' => '***'], $telework->__debugInfo());
    }

    public function testWithoutTelework(): void
    {
        $telework = Telework::withoutTelework(false, Country::Malte, 31);

        static::assertFalse($telework->teleworking());
        static::assertFalse($telework->workerAgreement());
        static::assertSame(Country::Malte, $telework->country());
        static::assertSame(31, $telework->daysPerMonth());
        static::assertSame(1, Telework::withoutTelework(null, null, 1)->daysPerMonth());
        $minimal = Telework::withoutTelework(null, null, null);
        static::assertNull($minimal->workerAgreement());
        static::assertNull($minimal->country());
        static::assertNull($minimal->daysPerMonth());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideOutOfRangeDays(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'thirty-two' => [32];
    }

    #[DataProvider('provideOutOfRangeDays')]
    public function testDaysOutOfRange(int $days): void
    {
        $params = ['min' => 1, 'max' => 31];

        InvalidValueAssertions::assertThrows(static fn(): Telework => Telework::withTelework(true, null, $days), 'days_per_month.out_of_range', $params);
        InvalidValueAssertions::assertThrows(static fn(): Telework => Telework::withoutTelework(null, null, $days), 'days_per_month.out_of_range', $params);
    }
}

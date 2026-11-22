<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;

final class CalendarDateTest extends TestCase
{
    public function testFromString(): void
    {
        $date = CalendarDate::fromString('2024-02-29');

        static::assertSame('2024-02-29', $date->toString());
        static::assertSame('2024-02-29', (string) $date);
        static::assertTrue($date->equals(CalendarDate::fromString('2024-02-29')));
        static::assertFalse($date->equals(CalendarDate::fromString('2024-02-28')));
        static::assertSame('0001-01-01', CalendarDate::fromString('0001-01-01')->toString());
        static::assertSame('9999-12-31', CalendarDate::fromString('9999-12-31')->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty' => ['', 'calendar_date.invalid_format'];
        yield 'slashes' => ['2024/02/29', 'calendar_date.invalid_format'];
        yield 'short year' => ['24-02-29', 'calendar_date.invalid_format'];
        yield 'time suffix' => ['2024-02-29T00:00:00Z', 'calendar_date.invalid_format'];
        yield 'trailing newline' => ["2024-02-29\n", 'calendar_date.invalid_format'];
        yield 'leading space' => [' 2024-02-29', 'calendar_date.invalid_format'];
        yield 'not a leap year' => ['2023-02-29', 'calendar_date.invalid_date'];
        yield 'month 13' => ['2024-13-01', 'calendar_date.invalid_date'];
        yield 'month 00' => ['2024-00-10', 'calendar_date.invalid_date'];
        yield 'day 32' => ['2024-01-32', 'calendar_date.invalid_date'];
        yield 'day 00' => ['2024-01-00', 'calendar_date.invalid_date'];
        yield 'year 0000' => ['0000-01-01', 'calendar_date.invalid_date'];
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid(string $value, string $code): void
    {
        try {
            CalendarDate::fromString($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame($code, $invalidValue->violationCode());
            static::assertStringNotContainsString(trim($value) === '' ? "\0" : trim($value), $invalidValue->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\DateEncoding;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class DateEncodingTest extends TestCase
{
    public function testItEncodesACalendarDateAsYearMonthDay(): void
    {
        static::assertSame('2026-03-09', DateEncoding::encodeCalendarDate(CalendarDate::fromString('2026-03-09')));
    }

    #[DataProvider('equivalentInstants')]
    public function testItReadsEveryOffsetNotation(string $value): void
    {
        static::assertSame('2022-02-01T15:40:00Z', DateEncoding::decodeInstant($value)->toIso8601());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function equivalentInstants(): iterable
    {
        yield 'Z' => ['2022-02-01T15:40:00Z'];
        yield 'colon offset' => ['2022-02-01T16:40:00+01:00'];
        yield 'compact offset' => ['2022-02-01T16:40:00+0100'];
        yield 'fraction' => ['2022-02-01T16:40:00.123+0100'];
        yield 'negative offset' => ['2022-02-01T10:40:00-05:00'];
    }

    #[DataProvider('invalidInstants')]
    public function testItRefusesAnythingElseWithoutRevealingTheValue(string $value): void
    {
        try {
            DateEncoding::decodeInstant($value);
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame('a date-time is not valid ISO 8601 with an offset', $exception->reason());
            static::assertStringNotContainsString($value, $exception->getMessage());
            static::assertNotNull($exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInstants(): iterable
    {
        yield 'no offset' => ['2022-02-01T16:40:00'];
        yield 'date only' => ['2022-02-01'];
        yield 'not a date' => ['yesterday'];
        yield 'impossible date' => ['2022-02-30T16:40:00Z'];
    }
}

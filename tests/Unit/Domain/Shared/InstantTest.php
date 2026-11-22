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

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

final class InstantTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValid(): iterable
    {
        yield 'zulu' => ['2022-02-01T16:40:00Z', '2022-02-01T16:40:00Z'];
        yield 'offset with colon' => ['2022-02-01T16:40:00+01:00', '2022-02-01T15:40:00Z'];
        yield 'offset without colon' => ['2022-02-01T16:40:00+0100', '2022-02-01T15:40:00Z'];
        yield 'negative offset' => ['2022-02-01T16:40:00-05:00', '2022-02-01T21:40:00Z'];
        yield 'fraction zulu' => ['2022-02-01T16:40:00.999Z', '2022-02-01T16:40:00Z'];
        yield 'fraction offset' => ['2022-02-01T16:40:00.123456+01:00', '2022-02-01T15:40:00Z'];
        yield 'long fraction' => ['2022-02-01T16:40:00.123456789Z', '2022-02-01T16:40:00Z'];
        yield 'day rollover' => ['2022-12-31T23:30:00-02:00', '2023-01-01T01:30:00Z'];
    }

    #[DataProvider('provideValid')]
    public function testFromIso8601(string $value, string $expected): void
    {
        $instant = Instant::fromIso8601($value);

        static::assertSame($expected, $instant->toIso8601());
        static::assertSame($expected, (string) $instant);
        static::assertSame('UTC', $instant->toDateTimeImmutable()->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty' => ['', 'instant.invalid_format'];
        yield 'date only' => ['2022-02-01', 'instant.invalid_format'];
        yield 'no offset' => ['2022-02-01T16:40:00', 'instant.invalid_format'];
        yield 'space separator' => ['2022-02-01 16:40:00Z', 'instant.invalid_format'];
        yield 'lower z' => ['2022-02-01T16:40:00z', 'instant.invalid_format'];
        yield 'bare fraction' => ['2022-02-01T16:40:00.Z', 'instant.invalid_format'];
        yield 'short offset' => ['2022-02-01T16:40:00+01', 'instant.invalid_format'];
        yield 'leading space' => [' 2022-02-01T16:40:00Z', 'instant.invalid_format'];
        yield 'trailing newline' => ["2022-02-01T16:40:00Z\n", 'instant.invalid_format'];
        yield 'missing day' => ['2023-02-29T10:00:00Z', 'instant.invalid_date'];
        yield 'hour 24' => ['2022-02-01T24:00:00Z', 'instant.invalid_date'];
        yield 'month 13' => ['2022-13-01T10:00:00Z', 'instant.invalid_date'];
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid(string $value, string $code): void
    {
        try {
            Instant::fromIso8601($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame($code, $invalidValue->violationCode());
        }
    }

    public function testFromDateTimeDropsFractionAndNormalisesToUtc(): void
    {
        $instant = Instant::fromDateTime(new DateTimeImmutable('2022-02-01 16:40:00.789', new DateTimeZone('Europe/Paris')));

        static::assertSame('2022-02-01T15:40:00Z', $instant->toIso8601());
    }

    public function testEquals(): void
    {
        $instant = Instant::fromIso8601('2022-02-01T16:40:00+01:00');

        static::assertTrue($instant->equals(Instant::fromIso8601('2022-02-01T15:40:00Z')));
        static::assertTrue($instant->equals(Instant::fromIso8601('2022-02-01T15:40:00.900Z')));
        static::assertFalse($instant->equals(Instant::fromIso8601('2022-02-01T15:40:01Z')));
    }
}

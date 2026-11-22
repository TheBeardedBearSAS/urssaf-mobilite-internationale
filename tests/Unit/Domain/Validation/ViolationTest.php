<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

final class ViolationTest extends TestCase
{
    public function testViolationAccessors(): void
    {
        $violation = Violation::error('worker.birthDate', 'calendar_date.invalid_format', 'Message.', ['expected_format' => 'Y-m-d']);

        static::assertSame('worker.birthDate', $violation->path());
        static::assertSame('calendar_date.invalid_format', $violation->code());
        static::assertSame('Message.', $violation->message());
        static::assertSame(['expected_format' => 'Y-m-d'], $violation->params());
        static::assertSame([], Violation::error('', 'a.b', 'M.')->params());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidPaths(): iterable
    {
        yield 'root' => [''];
        yield 'simple' => ['siren'];
        yield 'underscore start' => ['_a1'];
        yield 'dotted' => ['worker.birthDate'];
        yield 'indexed' => ['activities[2].country'];
        yield 'double index' => ['a[0][12]'];
    }

    #[DataProvider('provideValidPaths')]
    public function testValidPath(string $path): void
    {
        static::assertSame($path, Violation::error($path, 'a.b', 'M.')->path());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'leading digit' => ['1a'];
        yield 'leading dot' => ['.a'];
        yield 'trailing dot' => ['a.'];
        yield 'empty index' => ['a[]'];
        yield 'text index' => ['a[x]'];
        yield 'space' => ['a b'];
        yield 'trailing garbage' => ["a\n"];
    }

    #[DataProvider('provideInvalidPaths')]
    public function testInvalidPath(string $path): void
    {
        $this->expectException(InvariantViolation::class);

        Violation::error($path, 'a.b', 'M.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCodes(): iterable
    {
        yield 'no dot' => ['siren'];
        yield 'upper case' => ['Siren.empty'];
        yield 'leading digit' => ['1a.b'];
        yield 'empty reason' => ['a.'];
        yield 'dash' => ['a.b-c'];
        yield 'trailing garbage' => ["a.b\n"];
    }

    #[DataProvider('provideInvalidCodes')]
    public function testInvalidCode(string $code): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('A violation code must be');

        Violation::error('', $code, 'M.');
    }

    public function testInvalidPathMessage(): void
    {
        $this->expectExceptionMessage('A violation path must be');

        Violation::error('1a', 'a.b', 'M.');
    }

    public function testPathPrefix(): void
    {
        $violation = Violation::error('country', 'a.b', 'M.', ['k' => 1]);

        static::assertSame('activities[2].country', $violation->withPathPrefix('activities[2]')->path());
        static::assertSame('activities[2]', Violation::error('', 'a.b', 'M.')->withPathPrefix('activities[2]')->path());
        static::assertSame($violation, $violation->withPathPrefix(''));
        $prefixed = $violation->withPathPrefix('p');
        static::assertSame('a.b', $prefixed->code());
        static::assertSame('M.', $prefixed->message());
        static::assertSame(['k' => 1], $prefixed->params());
    }

    public function testEquals(): void
    {
        $base = Violation::error('p', 'a.b', 'M.', ['k' => 1]);

        static::assertTrue($base->equals(Violation::error('p', 'a.b', 'M.', ['k' => 1])));
        static::assertFalse($base->equals(Violation::error('q', 'a.b', 'M.', ['k' => 1])));
        static::assertFalse($base->equals(Violation::error('p', 'a.c', 'M.', ['k' => 1])));
        static::assertFalse($base->equals(Violation::error('p', 'a.b', 'N.', ['k' => 1])));
        static::assertFalse($base->equals(Violation::error('p', 'a.b', 'M.', ['k' => 2])));
    }
}

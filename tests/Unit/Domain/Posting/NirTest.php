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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\NirKey;

final class NirTest extends TestCase
{
    public function testNir(): void
    {
        $nir = Nir::fromString('2690549588157');

        static::assertSame('2690549588157', $nir->value());
        static::assertTrue($nir->equals(Nir::fromString('2690549588157')));
        static::assertFalse($nir->equals(Nir::fromString('2690549588158')));
        static::assertSame('100002A000000', Nir::fromString('100002A000000')->value(), 'Corsica, kept verbatim');
        static::assertSame('100002b000000', Nir::fromString('100002b000000')->value());
        static::assertSame(['value' => '***'], $nir->__debugInfo());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNirs(): iterable
    {
        yield 'empty' => [''];
        yield 'twelve' => ['269054958815'];
        yield 'fourteen' => ['26905495881570'];
        yield 'with key' => ['269054958815780'];
        yield 'letter C in 7th position' => ['100002C000000'];
        yield 'letter elsewhere' => ['A00002A000000'];
        yield 'trailing newline' => ["2690549588157\n"];
        yield 'leading space' => [' 2690549588157'];
    }

    #[DataProvider('provideInvalidNirs')]
    public function testInvalidNir(string $value): void
    {
        InvalidValueAssertions::assertThrows(
            static fn(): Nir => Nir::fromString($value),
            'nir.invalid_format',
            ['expected_format' => '[0-9]{6}[0-9AaBb][0-9]{6}'],
            trim($value),
        );
    }

    public function testNirKey(): void
    {
        $key = NirKey::fromString('80');

        static::assertSame('80', $key->value());
        static::assertTrue($key->equals(NirKey::fromString('80')));
        static::assertFalse($key->equals(NirKey::fromString('08')));
        static::assertSame(['value' => '***'], $key->__debugInfo());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNirKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'one digit' => ['8'];
        yield 'three digits' => ['800'];
        yield 'letter' => ['8A'];
        yield 'leading space' => [' 80'];
        yield 'trailing newline' => ["80\n"];
    }

    #[DataProvider('provideInvalidNirKeys')]
    public function testInvalidNirKey(string $value): void
    {
        InvalidValueAssertions::assertThrows(static fn(): NirKey => NirKey::fromString($value), 'nir_key.invalid_format', ['expected_format' => '[0-9]{2}']);
    }
}

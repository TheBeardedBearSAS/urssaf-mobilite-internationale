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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValid(): iterable
    {
        yield 'minimum' => ['0000000'];
        yield 'maximum' => [str_repeat('0', 30)];
        yield 'international with spaces' => ['+00 0 00 00 00 00'];
        yield 'letters accepted (no pattern)' => ['abcdefgh'];
        yield 'multibyte counted as characters' => [str_repeat('é', 30)];
    }

    #[DataProvider('provideValid')]
    public function testValid(string $value): void
    {
        $phone = PhoneNumber::fromString($value);

        static::assertSame($value, $phone->value());
        static::assertTrue($phone->equals(PhoneNumber::fromString($value)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['000000'];
        yield 'too long' => [str_repeat('0', 31)];
        yield 'too long multibyte' => [str_repeat('é', 31)];
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid(string $value): void
    {
        try {
            PhoneNumber::fromString($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('phone_number.invalid_length', $invalidValue->violationCode());
            static::assertSame(['min_length' => 7, 'max_length' => 30], $invalidValue->params());
        }
    }

    public function testNotEqual(): void
    {
        static::assertFalse(PhoneNumber::fromString('0000000')->equals(PhoneNumber::fromString('0000001')));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $phone = PhoneNumber::fromString('0123456789');

        static::assertSame(['value' => '***'], $phone->__debugInfo());
        static::assertStringNotContainsString('0123456789', print_r($phone, true));
    }

    public function testCannotBeSerialized(): void
    {
        $value = PhoneNumber::fromString('0000000001');

        $this->expectException(PersonalDataNotSerializable::class);
        serialize($value);
    }

    public function testCannotBeUnserialized(): void
    {
        $this->expectException(PersonalDataNotSerializable::class);

        unserialize(\sprintf('O:%d:"%s":1:{s:5:"value";s:1:"x";}', \strlen(PhoneNumber::class), PhoneNumber::class));
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;

final class EmailAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValid(): iterable
    {
        yield 'plain' => ['test@example.invalid'];
        yield 'no dot in domain' => ['a@b'];
        yield 'plus and case kept' => ['Some.One+tag@Example.Invalid'];
        yield 'unicode' => ['tést@exämple.invalid'];
    }

    #[DataProvider('provideValid')]
    public function testValid(string $value): void
    {
        $email = EmailAddress::fromString($value);

        static::assertSame($value, $email->value());
        static::assertTrue($email->equals(EmailAddress::fromString($value)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty' => [''];
        yield 'no at' => ['test.example.invalid'];
        yield 'two ats' => ['a@b@c'];
        yield 'empty local' => ['@example.invalid'];
        yield 'empty domain' => ['test@'];
        yield 'only at' => ['@'];
        yield 'space inside' => ['te st@example.invalid'];
        yield 'trailing space' => ['test@example.invalid '];
        yield 'trailing newline' => ["test@example.invalid\n"];
        yield 'tab in domain' => ["test@exa\tmple.invalid"];
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid(string $value): void
    {
        try {
            EmailAddress::fromString($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('email_address.invalid_format', $invalidValue->violationCode());
            static::assertSame(['expected_format' => 'local@domain'], $invalidValue->params());
            static::assertStringNotContainsString('example', $invalidValue->getMessage());
        }
    }

    public function testEqualsIsCaseSensitive(): void
    {
        static::assertFalse(EmailAddress::fromString('a@b.invalid')->equals(EmailAddress::fromString('A@b.invalid')));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $email = EmailAddress::fromString('test@example.invalid');

        static::assertSame(['value' => '***'], $email->__debugInfo());
        static::assertStringNotContainsString('example.invalid', print_r($email, true));
    }

    public function testCannotBeSerialized(): void
    {
        $value = EmailAddress::fromString('test@example.invalid');

        $this->expectException(PersonalDataNotSerializable::class);
        serialize($value);
    }

    public function testCannotBeUnserialized(): void
    {
        $this->expectException(PersonalDataNotSerializable::class);

        unserialize(\sprintf('O:%d:"%s":1:{s:5:"value";s:1:"x";}', \strlen(EmailAddress::class), EmailAddress::class));
    }
}

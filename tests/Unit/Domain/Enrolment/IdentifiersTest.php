<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Enrolment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

final class IdentifiersTest extends TestCase
{
    public function testEnrolmentId(): void
    {
        $id = EnrolmentId::fromString('3FA85F64-5717-4562-B3FC-2C963F66AFA6');

        static::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', $id->value());
        static::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', (string) $id);
        static::assertTrue($id->equals(EnrolmentId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa6')));
        static::assertFalse($id->equals(EnrolmentId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa7')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'no dashes' => ['3fa85f6457174562b3fc2c963f66afa6'];
        yield 'too short' => ['3fa85f64-5717-4562-b3fc-2c963f66afa'];
        yield 'too long' => ['3fa85f64-5717-4562-b3fc-2c963f66afa66'];
        yield 'non hex' => ['3fa85f64-5717-4562-b3fc-2c963f66afag'];
        yield 'prefixed' => [' 3fa85f64-5717-4562-b3fc-2c963f66afa6'];
        yield 'trailing newline' => ["3fa85f64-5717-4562-b3fc-2c963f66afa6\n"];
    }

    #[DataProvider('provideInvalidIds')]
    public function testInvalidEnrolmentId(string $value): void
    {
        $this->assertInvalid(static fn(): object => EnrolmentId::fromString($value), 'enrolment_id.invalid_format');
    }

    public function testSiren(): void
    {
        $siren = Siren::fromString('123456789');

        static::assertSame('123456789', $siren->value());
        static::assertSame('123456789', (string) $siren);
        static::assertTrue($siren->equals(Siren::fromString('123456789')));
        static::assertFalse($siren->equals(Siren::fromString('123456780')));
        static::assertSame('abcdefghi', Siren::fromString('abcdefghi')->value(), 'no pattern, no checksum');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidSirens(): iterable
    {
        yield 'empty' => [''];
        yield 'eight' => ['12345678'];
        yield 'ten' => ['1234567890'];
        yield 'eight characters, nine bytes' => ['1234567é'];
    }

    #[DataProvider('provideInvalidSirens')]
    public function testInvalidSiren(string $value): void
    {
        $this->assertInvalid(static fn(): object => Siren::fromString($value), 'siren.invalid_length');
    }

    public function testNic(): void
    {
        $nic = Nic::fromString('12345');

        static::assertSame('12345', $nic->value());
        static::assertSame('12345', (string) $nic);
        static::assertTrue($nic->equals(Nic::fromString('12345')));
        static::assertFalse($nic->equals(Nic::fromString('12346')));
        static::assertSame('éééöé', Nic::fromString('éééöé')->value(), 'five characters, ten bytes');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNics(): iterable
    {
        yield 'empty' => [''];
        yield 'four' => ['1234'];
        yield 'six' => ['123456'];
    }

    #[DataProvider('provideInvalidNics')]
    public function testInvalidNic(string $value): void
    {
        $this->assertInvalid(static fn(): object => Nic::fromString($value), 'nic.invalid_length');
    }

    /**
     * @param callable(): object $build
     *
     * @param-immediately-invoked-callable $build
     */
    private function assertInvalid(callable $build, string $code): void
    {
        try {
            $build();
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame($code, $invalidValue->violationCode());
        }
    }
}

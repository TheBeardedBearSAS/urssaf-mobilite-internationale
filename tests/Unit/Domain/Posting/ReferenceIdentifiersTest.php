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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\CompanyReference;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ExternalAccountNumber;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceNic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceSiren;

final class ReferenceIdentifiersTest extends TestCase
{
    public function testSiren(): void
    {
        $siren = ReferenceSiren::fromString('404833048');

        static::assertSame('404833048', $siren->value());
        static::assertTrue($siren->equals(ReferenceSiren::fromString('404833048')));
        static::assertFalse($siren->equals(ReferenceSiren::fromString('404833049')));
        static::assertSame('ABCDEFGHZ', ReferenceSiren::fromString('ABCDEFGHZ')->value(), 'upper-case letters allowed');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidSirens(): iterable
    {
        yield 'empty' => [''];
        yield 'eight' => ['40483304'];
        yield 'ten' => ['4048330480'];
        yield 'lower case' => ['40483304a'];
        yield 'trailing newline' => ["404833048\n"];
        yield 'leading space' => [' 40483304'];
    }

    #[DataProvider('provideInvalidSirens')]
    public function testInvalidSiren(string $value): void
    {
        InvalidValueAssertions::assertThrows(static fn(): ReferenceSiren => ReferenceSiren::fromString($value), 'siren.invalid_format', ['expected_format' => '[0-9A-Z]{9}']);
    }

    public function testNic(): void
    {
        $nic = ReferenceNic::fromString('00022');

        static::assertSame('00022', $nic->value());
        static::assertTrue($nic->equals(ReferenceNic::fromString('00022')));
        static::assertFalse($nic->equals(ReferenceNic::fromString('0022')));
        static::assertSame('', ReferenceNic::fromString('')->value(), 'no minimum length (api-reference §5.14)');
        static::assertSame('1', ReferenceNic::fromString('1')->value());
    }

    /**
     * @return iterable<string, array{string, string, array<string, scalar>}>
     */
    public static function provideInvalidNics(): iterable
    {
        yield 'six digits' => ['000222', 'nic.too_long', ['max_length' => 5]];
        yield 'six multibyte characters' => ['éééééé', 'nic.too_long', ['max_length' => 5]];
        yield 'letter' => ['0002A', 'nic.invalid_format', ['expected_format' => '[0-9]*']];
        yield 'three multibyte characters, six bytes' => ['ééé', 'nic.invalid_format', ['expected_format' => '[0-9]*']];
        yield 'trailing newline' => ["0002\n", 'nic.invalid_format', ['expected_format' => '[0-9]*']];
        yield 'leading letter' => ['a0002', 'nic.invalid_format', ['expected_format' => '[0-9]*']];
    }

    /**
     * @param array<string, scalar> $params
     */
    #[DataProvider('provideInvalidNics')]
    public function testInvalidNic(string $value, string $code, array $params): void
    {
        InvalidValueAssertions::assertThrows(static fn(): ReferenceNic => ReferenceNic::fromString($value), $code, $params);
    }

    public function testExternalAccountNumber(): void
    {
        $number = ExternalAccountNumber::fromString('000000000000000018');

        static::assertSame('000000000000000018', $number->value());
        static::assertTrue($number->equals(ExternalAccountNumber::fromString('000000000000000018')));
        static::assertFalse($number->equals(ExternalAccountNumber::fromString('000000000000000019')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidAccountNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'seventeen' => ['00000000000000001'];
        yield 'nineteen' => ['0000000000000000001'];
        yield 'letter' => ['00000000000000001A'];
        yield 'leading space' => [' 00000000000000001'];
        yield 'trailing newline' => ["000000000000000018\n"];
    }

    #[DataProvider('provideInvalidAccountNumbers')]
    public function testInvalidExternalAccountNumber(string $value): void
    {
        InvalidValueAssertions::assertThrows(
            static fn(): ExternalAccountNumber => ExternalAccountNumber::fromString($value),
            'external_account_number.invalid_format',
            ['expected_format' => '[0-9]{18}'],
        );
    }

    public function testCompanyReference(): void
    {
        $siren = ReferenceSiren::fromString('404833048');
        $nic = ReferenceNic::fromString('00022');
        $number = ExternalAccountNumber::fromString('000000000000000018');

        $reference = CompanyReference::of($siren, $nic, $number);

        static::assertSame($siren, $reference->siren());
        static::assertSame($nic, $reference->nic());
        static::assertSame($number, $reference->externalAccountNumber());
        static::assertNull(CompanyReference::of($siren, $nic, null)->externalAccountNumber());
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;

final class CountryTest extends TestCase
{
    public function testMatchesTheContract(): void
    {
        $values = self::contractCountries();

        static::assertCount(226, $values);
        static::assertSame('', $values[0]);
        static::assertSame(
            \array_slice($values, 1),
            array_map(static fn(Country $country): string => $country->value, Country::cases()),
        );
        static::assertCount(225, Country::cases());
    }

    public function testCaseNamesAreDerivedFromTheWireValue(): void
    {
        foreach (Country::cases() as $case) {
            $words = preg_split('/[ \-\'_]+/', $case->value, -1, PREG_SPLIT_NO_EMPTY);
            static::assertIsArray($words);
            static::assertSame(
                implode('', array_map(static fn(string $word): string => ucfirst(strtolower($word)), $words)),
                $case->name,
            );
        }
        static::assertSame('PAYS-BAS', Country::PaysBas->value);
        static::assertSame("COTE D'IVOIRE", Country::CoteDIvoire->value);
        static::assertSame('REPUBLIQUE DE COREE', Country::RepubliqueDeCoree->value);
        static::assertSame('COREE DU SUD', Country::CoreeDuSud->value);
    }

    public function testFromWire(): void
    {
        static::assertSame(Country::France, Country::fromWire('FRANCE'));
        static::assertSame(Country::PaysBas, Country::fromWire('PAYS-BAS'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownValues(): iterable
    {
        yield 'empty string of the contract' => [''];
        yield 'lower case' => ['france'];
        yield 'accented' => ['CÔTE D’IVOIRE'];
        yield 'unknown' => ['ATLANTIDE'];
        yield 'padded' => [' FRANCE'];
    }

    #[DataProvider('provideUnknownValues')]
    public function testUnknownValueIsRefusedWithoutEchoingIt(string $value): void
    {
        try {
            Country::fromWire($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('country.unknown', $invalidValue->violationCode());
            static::assertSame('Invalid country: the value is not in the list of the contract.', $invalidValue->getMessage());
            static::assertSame([], $invalidValue->params());
        }
    }

    /**
     * @return list<string>
     */
    private static function contractCountries(): array
    {
        $json = file_get_contents(\dirname(__DIR__, 4) . '/docs/openapi/mobi-prd.json');
        static::assertIsString($json);
        /** @var array{components: array{schemas: array{Pays: array{enum: list<string>}}}} $document */
        $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $document['components']['schemas']['Pays']['enum'];
    }
}

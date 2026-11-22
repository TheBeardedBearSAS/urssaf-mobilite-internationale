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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;

final class PersonalDataTest extends TestCase
{
    public function testEveryPropertyIsMaskedInDebugOutput(): void
    {
        $address = Address::of(Label::fromString('1 rue Exemple'), null, Label::fromString('Villexemple'), null, Country::Italie);

        static::assertSame(
            ['line' => '***', 'complement' => '***', 'city' => '***', 'postalCode' => '***', 'country' => '***'],
            $address->__debugInfo(),
        );
        static::assertSame(['value' => '***'], Label::fromString('Nom exemple')->__debugInfo());
        $dump = print_r($address, true);
        static::assertStringNotContainsString('Exemple', $dump);
        static::assertStringNotContainsString('ITALIE', $dump);
    }

    public function testCannotBeSerialized(): void
    {
        try {
            serialize(Label::fromString('Nom exemple'));
            static::fail('PersonalDataNotSerializable expected.');
        } catch (PersonalDataNotSerializable $exception) {
            static::assertSame('Objects carrying personal data must never be serialized.', $exception->getMessage());
        }
    }

    public function testCannotBeUnserialized(): void
    {
        $this->expectException(PersonalDataNotSerializable::class);

        unserialize(\sprintf('O:%d:"%s":1:{s:5:"value";s:3:"Nom";}', \strlen(Label::class), Label::class));
    }
}

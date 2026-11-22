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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\NirKey;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostalCode;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

final class WorkerTest extends TestCase
{
    public function testAddress(): void
    {
        $line = Label::fromString('1 rue Exemple');
        $complement = Label::fromString('Batiment A');
        $city = Label::fromString('Villexemple');
        $postalCode = PostalCode::fromString('00000');
        $address = Address::of($line, $complement, $city, $postalCode, Country::Italie);

        static::assertSame($line, $address->line());
        static::assertSame($complement, $address->complement());
        static::assertSame($city, $address->city());
        static::assertSame($postalCode, $address->postalCode());
        static::assertSame(Country::Italie, $address->country());
    }

    public function testCompleteWorker(): void
    {
        $nir = Nir::fromString('0000000000000');
        $key = NirKey::fromString('00');
        $usageName = Label::fromString('Exemple');
        $birthName = Label::fromString('Naissance');
        $firstName = Label::fromString('Prenom');
        $birthDate = CalendarDate::fromString('2000-01-01');
        $birthPlace = Label::fromString('Villexemple');
        $address = Address::of(Label::fromString('1 rue Exemple'), null, null, null, Country::France);
        $email = EmailAddress::fromString('test@example.invalid');
        $phone1 = PhoneNumber::fromString('0000000001');
        $phone2 = PhoneNumber::fromString('0000000002');

        $worker = Worker::of($nir, $key, $usageName, $birthName, $firstName, $birthDate, $birthPlace, Country::Espagne, Country::France, $address, $email, $phone1, $phone2);

        static::assertSame($nir, $worker->nir());
        static::assertSame($key, $worker->nirKey());
        static::assertSame($usageName, $worker->usageName());
        static::assertSame($birthName, $worker->birthName());
        static::assertSame($firstName, $worker->firstName());
        static::assertSame($birthDate, $worker->birthDate());
        static::assertSame($birthPlace, $worker->birthPlace());
        static::assertSame(Country::Espagne, $worker->birthCountry());
        static::assertSame(Country::France, $worker->nationality());
        static::assertSame($address, $worker->address());
        static::assertSame($email, $worker->email());
        static::assertSame($phone1, $worker->phone1());
        static::assertSame($phone2, $worker->phone2());
        static::assertNotContains('0000000000000', $worker->__debugInfo());
        static::assertStringNotContainsString('0000000000000', print_r($worker, true));
    }

    public function testMinimalWorker(): void
    {
        $worker = Worker::of(
            Nir::fromString('0000000000000'),
            null,
            Label::fromString('Exemple'),
            null,
            Label::fromString('Prenom'),
            CalendarDate::fromString('2000-01-01'),
            null,
            null,
            Country::France,
            null,
            null,
            null,
            null,
        );

        static::assertNull($worker->nirKey());
        static::assertNull($worker->birthName());
        static::assertNull($worker->birthPlace());
        static::assertNull($worker->birthCountry());
        static::assertNull($worker->address());
        static::assertNull($worker->email());
        static::assertNull($worker->phone1());
        static::assertNull($worker->phone2());
    }
}

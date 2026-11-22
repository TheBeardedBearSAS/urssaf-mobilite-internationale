<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\CompanyReference;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declarant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ExternalAccountNumber;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostalCode;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceNic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceSiren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

/**
 * Visibly synthetic parties (no real person, company, address or identifier), built from the domain constructors.
 */
final class SyntheticParties
{
    public static function slug(string $name): string
    {
        return strtolower(str_replace(' ', '-', $name));
    }

    public static function email(string $local): EmailAddress
    {
        return EmailAddress::fromString($local . '@example.invalid');
    }

    /**
     * Every member set; the country is Italy.
     */
    public static function fullAddress(string $line): Address
    {
        return Address::of(
            Label::fromString($line),
            Label::fromString($line . ' complement'),
            Label::fromString($line . ' city'),
            PostalCode::fromString('00000'),
            Country::Italie,
        );
    }

    /**
     * Known address (full), e-mail derived from the name.
     */
    public static function knownCompany(string $name): Company
    {
        return Company::withKnownAddress(
            Label::fromString($name),
            self::fullAddress($name . ' street'),
            self::email(self::slug($name)),
        );
    }

    public static function unknownCompany(string $name): Company
    {
        return Company::withUnknownAddress(Label::fromString($name), self::email(self::slug($name)));
    }

    public static function declarant(): Declarant
    {
        return Declarant::of(
            Label::fromString('Declarant Test'),
            self::email('declarant'),
            PhoneNumber::fromString('0000000001'),
            null,
        );
    }

    public static function declarantWithSecondPhone(): Declarant
    {
        return Declarant::of(
            Label::fromString('Declarant Test'),
            self::email('declarant'),
            PhoneNumber::fromString('0000000001'),
            PhoneNumber::fromString('0000000002'),
        );
    }

    public static function reference(): CompanyReference
    {
        return CompanyReference::of(ReferenceSiren::fromString('000000000'), ReferenceNic::fromString('00001'), null);
    }

    public static function referenceWithAccount(): CompanyReference
    {
        return CompanyReference::of(
            ReferenceSiren::fromString('000000000'),
            ReferenceNic::fromString('00001'),
            ExternalAccountNumber::fromString('000000000000000001'),
        );
    }
}

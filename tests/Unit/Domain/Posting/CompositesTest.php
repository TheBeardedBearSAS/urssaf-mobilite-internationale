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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityCriterion;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityType;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declarant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Signature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

final class CompositesTest extends TestCase
{
    public function testCompanyWithKnownAddress(): void
    {
        $name = Label::fromString('Entreprise exemple');
        $address = Address::of(Label::fromString('1 rue Exemple'), null, null, null, Country::Italie);
        $email = EmailAddress::fromString('company@example.invalid');

        $company = Company::withKnownAddress($name, $address, $email);

        static::assertSame($name, $company->name());
        static::assertTrue($company->addressKnown());
        static::assertSame($address, $company->address());
        static::assertSame($email, $company->email());
        static::assertNull(Company::withKnownAddress($name, null, $email)->address(), 'the address stays optional');
        static::assertStringNotContainsString('Entreprise exemple', print_r($company, true));
    }

    public function testCompanyWithUnknownAddress(): void
    {
        $company = Company::withUnknownAddress(Label::fromString('Entreprise exemple'), EmailAddress::fromString('company@example.invalid'));

        static::assertFalse($company->addressKnown());
        static::assertNull($company->address());
    }

    public function testDeclarant(): void
    {
        $name = Label::fromString('Nom exemple');
        $email = EmailAddress::fromString('test@example.invalid');
        $phone1 = PhoneNumber::fromString('0000000001');
        $phone2 = PhoneNumber::fromString('0000000002');

        $declarant = Declarant::of($name, $email, $phone1, $phone2);

        static::assertSame($name, $declarant->name());
        static::assertSame($email, $declarant->email());
        static::assertSame($phone1, $declarant->phone1());
        static::assertSame($phone2, $declarant->phone2());
        static::assertNull(Declarant::of($name, $email, $phone1, null)->phone2());
        static::assertSame(['name' => '***', 'email' => '***', 'phone1' => '***', 'phone2' => '***'], $declarant->__debugInfo());
    }

    public function testSignature(): void
    {
        $signature = Signature::of(true, false);

        static::assertTrue($signature->personalDataAuthorisation());
        static::assertFalse($signature->declarationOnHonour());
        static::assertFalse(Signature::of(false, true)->personalDataAuthorisation());
        static::assertTrue(Signature::of(false, true)->declarationOnHonour());
    }

    public function testHostAddress(): void
    {
        $address = Address::of(Label::fromString('1 rue Exemple'), null, null, null, Country::Italie);

        $elsewhere = HostAddress::elsewhere($address);
        $usual = HostAddress::usualHome();

        static::assertFalse($elsewhere->usualAddress());
        static::assertSame($address, $elsewhere->address());
        static::assertNull(HostAddress::elsewhere(null)->address());
        static::assertTrue($usual->usualAddress());
        static::assertNull($usual->address());
        static::assertSame(['usualAddress' => '***', 'address' => '***'], $elsewhere->__debugInfo());
    }

    public function testSubstantialActivity(): void
    {
        $activity = SubstantialActivity::of(Answer::No, Country::Italie, ActivityCriterion::Remuneration);

        static::assertSame(Answer::No, $activity->answer());
        static::assertSame(Country::Italie, $activity->countryOfInterest());
        static::assertSame(ActivityCriterion::Remuneration, $activity->criterion());
        static::assertNull(SubstantialActivity::of(Answer::NotApplicable, null, null)->criterion());
    }

    public function testActivityDetails(): void
    {
        $details = ActivityDetails::of(true, false, null, true, false, null);

        static::assertTrue($details->replacesAnotherEmployee());
        static::assertFalse($details->affiliatedToFrenchSocialSecurity());
        static::assertNull($details->remunerationModifiable());
        static::assertTrue($details->dismissalByHostCompany());
        static::assertFalse($details->disciplinarySanctionsByHostCompany());
        static::assertNull($details->foreignEmploymentContract());
        $reversed = ActivityDetails::of(null, true, false, null, true, false);
        static::assertTrue($reversed->affiliatedToFrenchSocialSecurity());
        static::assertFalse($reversed->remunerationModifiable());
        static::assertFalse($reversed->foreignEmploymentContract());
    }

    public function testActivityDeclaration(): void
    {
        $company = Company::withUnknownAddress(Label::fromString('Entreprise exemple'), EmailAddress::fromString('company@example.invalid'));
        $natureOther = FreeText::fromString('Autre nature');
        $details = ActivityDetails::of(null, null, null, null, null, null);

        $activity = ActivityDeclaration::of(ActivityNature::Autre, Country::Italie, $company, ActivityType::Autre, $natureOther, Country::Malte, true, false, true, false, $details);

        static::assertSame(ActivityNature::Autre, $activity->nature());
        static::assertSame(Country::Italie, $activity->country());
        static::assertSame($company, $activity->company());
        static::assertSame(ActivityType::Autre, $activity->type());
        static::assertSame($natureOther, $activity->natureOther());
        static::assertSame(Country::Malte, $activity->homeBaseCountry());
        static::assertTrue($activity->frenchActivity());
        static::assertFalse($activity->atLeast25PercentInFrance());
        static::assertTrue($activity->invoiceIssued());
        static::assertFalse($activity->contractSigned());
        static::assertSame($details, $activity->details());
        static::assertStringNotContainsString('Autre nature', print_r($activity, true));
    }
}

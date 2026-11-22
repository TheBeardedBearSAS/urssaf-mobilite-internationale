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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\CompanyReference;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declarant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceNic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceSiren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Signature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input\ViolationSummary;

final class PostingRequestTest extends TestCase
{
    public function testTypedRequest(): void
    {
        $declarant = Declarant::of(Label::fromString('Nom exemple'), EmailAddress::fromString('test@example.invalid'), PhoneNumber::fromString('0000000001'), null);
        $company = self::company();
        $reference = CompanyReference::of(ReferenceSiren::fromString('000000000'), ReferenceNic::fromString('00022'), null);
        $signature = Signature::of(true, true);
        $details = self::details(null);

        $declaration = Declaration::of($company, $reference, $signature, $details);
        $request = PostingRequest::of($declarant, $declaration);

        static::assertSame($declarant, $request->declarant());
        static::assertSame($declaration, $request->declaration());
        static::assertSame($company, $declaration->declaringCompany());
        static::assertSame($reference, $declaration->companyReference());
        static::assertSame($signature, $declaration->signature());
        static::assertSame($details, $declaration->posting());
        static::assertSame(['declarant' => '***', 'declaration' => '***'], $request->__debugInfo());
        $this->expectException(PersonalDataNotSerializable::class);
        serialize($request);
    }

    public function testPostingDetails(): void
    {
        $start = CalendarDate::fromString('2026-06-30');
        $end = CalendarDate::fromString('2026-01-01');
        $telework = Telework::withoutTelework(null, null, null);
        $host = HostAddress::usualHome();
        $substantial = SubstantialActivity::of(Answer::Yes, null, null);
        $declared = self::activity();
        $current = [self::activity(), self::activity()];
        $comment = FreeText::fromString('Commentaire');

        $details = PostingDetails::of(null, $start, $end, $telework, $host, Answer::Yes, Answer::No, $substantial, $declared, $current, $comment);

        static::assertNull($details->worker());
        static::assertSame($start, $details->startDate(), 'no order between the dates (ADR 0008)');
        static::assertSame($end, $details->endDate());
        static::assertSame($telework, $details->telework());
        static::assertSame($host, $details->hostAddress());
        static::assertSame(Answer::Yes, $details->sameActivitySector());
        static::assertSame(Answer::No, $details->durableInfrastructure());
        static::assertSame($substantial, $details->substantialActivity());
        static::assertSame($declared, $details->declaredActivity());
        static::assertSame($current, $details->currentActivities());
        static::assertSame($comment, $details->comment());
    }

    public function testDefaultPolicyValidatesTheSwaggerOnly(): void
    {
        $policy = PostingValidationPolicy::default();

        static::assertFalse($policy->appliesPdfTeleworkRules());
        static::assertTrue($policy->validate(self::request(Telework::withTelework(null, null, 2)))->isValid());
    }

    public function testPdfTeleworkRules(): void
    {
        $policy = PostingValidationPolicy::default()->withPdfTeleworkRules();

        static::assertTrue($policy->appliesPdfTeleworkRules());
        static::assertSame(
            ['declaration.posting.telework.country field.required', 'declaration.posting.telework.workerAgreement field.required'],
            ViolationSummary::of($policy->validate(self::request(Telework::withTelework(null, null, 2)))),
        );
        static::assertSame(
            ['declaration.posting.telework.country field.required'],
            ViolationSummary::of($policy->validate(self::request(Telework::withTelework(false, null, 2)))),
            'present, not necessarily true',
        );
        static::assertSame(
            ['declaration.posting.telework.workerAgreement field.required'],
            ViolationSummary::of($policy->validate(self::request(Telework::withTelework(null, Country::Italie, 2)))),
        );
        static::assertTrue($policy->validate(self::request(Telework::withTelework(true, Country::Italie, 2)))->isValid());
        static::assertTrue($policy->validate(self::request(Telework::withoutTelework(null, null, null)))->isValid());
        static::assertTrue($policy->validate(self::request(null))->isValid());
        static::assertSame(
            'This field is required when teleworking (PDF rule).',
            $policy->validate(self::request(Telework::withTelework(true, null, 2)))->all()[0]->message(),
        );
    }

    private static function request(?Telework $telework): PostingRequest
    {
        $declarant = Declarant::of(Label::fromString('Nom exemple'), EmailAddress::fromString('test@example.invalid'), PhoneNumber::fromString('0000000001'), null);
        $reference = CompanyReference::of(ReferenceSiren::fromString('000000000'), ReferenceNic::fromString('00022'), null);

        return PostingRequest::of($declarant, Declaration::of(self::company(), $reference, Signature::of(true, true), self::details($telework)));
    }

    private static function details(?Telework $telework): PostingDetails
    {
        return PostingDetails::of(null, null, null, $telework, null, null, null, null, null, [], null);
    }

    private static function company(): Company
    {
        return Company::withUnknownAddress(Label::fromString('Entreprise exemple'), EmailAddress::fromString('company@example.invalid'));
    }

    private static function activity(): ActivityDeclaration
    {
        return ActivityDeclaration::of(ActivityNature::Salarie, Country::Italie, self::company(), null, null, null, null, null, null, null, null);
    }
}

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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityCriterion;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\NirKey;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Signature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

/**
 * Visibly synthetic posting requests, built from the domain constructors.
 */
final class SyntheticPostings
{
    /**
     * Every optional member set: two current activities (a complete one and a bare one).
     */
    public static function full(): PostingRequest
    {
        return PostingRequest::of(
            SyntheticParties::declarantWithSecondPhone(),
            Declaration::of(
                SyntheticParties::knownCompany('Declaring'),
                SyntheticParties::referenceWithAccount(),
                Signature::of(true, false),
                PostingDetails::of(
                    self::completeWorker(),
                    CalendarDate::fromString('2026-02-01'),
                    CalendarDate::fromString('2026-08-31'),
                    Telework::withTelework(true, Country::Autriche, 3),
                    HostAddress::elsewhere(SyntheticParties::fullAddress('Host street')),
                    Answer::Yes,
                    Answer::NotApplicable,
                    SubstantialActivity::of(Answer::No, Country::Pologne, ActivityCriterion::TempsTravail),
                    SyntheticActivities::complete('Declared', ActivityDetails::of(true, false, true, false, true, false)),
                    [
                        SyntheticActivities::complete('Current one', ActivityDetails::of(false, true, false, true, false, true)),
                        SyntheticActivities::bare('Current two', null),
                    ],
                    FreeText::fromString('Free comment'),
                ),
            ),
        );
    }

    /**
     * No optional member at all.
     */
    public static function minimal(): PostingRequest
    {
        return self::withDetails(PostingDetails::of(null, null, null, null, null, null, null, null, null, [], null));
    }

    /**
     * Minimal declarant, company and reference around the given posting.
     */
    public static function withDetails(PostingDetails $details): PostingRequest
    {
        return PostingRequest::of(
            SyntheticParties::declarant(),
            Declaration::of(
                SyntheticParties::unknownCompany('Declaring'),
                SyntheticParties::reference(),
                Signature::of(false, true),
                $details,
            ),
        );
    }

    private static function completeWorker(): Worker
    {
        return Worker::of(
            Nir::fromString('0000000000000'),
            NirKey::fromString('00'),
            Label::fromString('Usage Test'),
            Label::fromString('Birth Test'),
            Label::fromString('First Test'),
            CalendarDate::fromString('2000-01-31'),
            Label::fromString('Birthplace Test'),
            Country::France,
            Country::Belgique,
            SyntheticParties::fullAddress('Worker street'),
            SyntheticParties::email('worker'),
            PhoneNumber::fromString('0000000003'),
            PhoneNumber::fromString('0000000004'),
        );
    }
}

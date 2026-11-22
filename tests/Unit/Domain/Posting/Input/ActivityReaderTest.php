<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityType;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\ActivityDeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\ActivityReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;

final class ActivityReaderTest extends TestCase
{
    public function testCompleteActivity(): void
    {
        $reader = new DraftReader();

        $activity = (new ActivityReader($reader))->read('activity', PostingDrafts::activity());

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($activity);
        static::assertSame(ActivityNature::Salarie, $activity->nature());
        static::assertSame(Country::Italie, $activity->country());
        static::assertSame('Entreprise exemple', $activity->company()->name()->value());
        static::assertSame(ActivityType::Autre, $activity->type());
        static::assertSame('Autre nature', $activity->natureOther()?->value());
        static::assertSame(Country::Malte, $activity->homeBaseCountry());
        static::assertFalse($activity->frenchActivity());
        static::assertTrue($activity->atLeast25PercentInFrance());
        static::assertFalse($activity->invoiceIssued());
        static::assertTrue($activity->contractSigned());
        $details = $activity->details();
        static::assertNotNull($details);
        static::assertTrue($details->replacesAnotherEmployee());
        static::assertFalse($details->affiliatedToFrenchSocialSecurity());
        static::assertNull($details->remunerationModifiable());
        static::assertTrue($details->dismissalByHostCompany());
        static::assertFalse($details->disciplinarySanctionsByHostCompany());
        static::assertNull($details->foreignEmploymentContract());
    }

    public function testMinimalActivity(): void
    {
        $reader = new DraftReader();
        $draft = new ActivityDeclarationDraft('TOUS', 'FRANCE', PostingDrafts::company(), null, null, null, null, null, null, null, null);

        $activity = (new ActivityReader($reader))->read('activity', $draft);

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($activity);
        static::assertNull($activity->type());
        static::assertNull($activity->natureOther());
        static::assertNull($activity->homeBaseCountry());
        static::assertNull($activity->frenchActivity());
        static::assertNull($activity->details());
    }

    public function testEmptyActivity(): void
    {
        $reader = new DraftReader();
        $draft = new ActivityDeclarationDraft(null, null, null, null, null, null, null, null, null, null, null);

        static::assertNull((new ActivityReader($reader))->read('activity', $draft));
        static::assertSame(
            ['activity.nature field.required', 'activity.country field.required', 'activity.company field.required'],
            ViolationSummary::of($reader->result()),
        );
    }

    /**
     * @return iterable<string, array{ActivityDeclarationDraft, string}>
     */
    public static function provideSingleMissingField(): iterable
    {
        $company = PostingDrafts::company();
        yield 'nature' => [new ActivityDeclarationDraft(null, 'FRANCE', $company, null, null, null, null, null, null, null, null), 'nature'];
        yield 'country' => [new ActivityDeclarationDraft('SALARIE', null, $company, null, null, null, null, null, null, null, null), 'country'];
        yield 'company' => [new ActivityDeclarationDraft('SALARIE', 'FRANCE', null, null, null, null, null, null, null, null, null), 'company'];
    }

    #[DataProvider('provideSingleMissingField')]
    public function testSingleMissingField(ActivityDeclarationDraft $draft, string $field): void
    {
        $reader = new DraftReader();

        static::assertNull((new ActivityReader($reader))->read('activity', $draft));
        static::assertSame(['activity.' . $field . ' field.required'], ViolationSummary::of($reader->result()));
    }

    public function testInvalidValues(): void
    {
        $reader = new DraftReader();
        $draft = new ActivityDeclarationDraft('salarie', 'Italie', PostingDrafts::company(), 'autre', '', '', null, null, null, null, null);

        static::assertNull((new ActivityReader($reader))->read('activity', $draft));
        static::assertSame(
            [
                'activity.nature activity_nature.unknown',
                'activity.country country.unknown',
                'activity.type activity_type.unknown',
                'activity.homeBaseCountry country.unknown',
            ],
            ViolationSummary::of($reader->result()),
        );
    }
}

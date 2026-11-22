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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityCriterion;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\ActivityDeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\AddressDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\HostAddressDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingDetailsDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingDetailsReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\SubstantialActivityDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\TeleworkDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\WorkerDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;

final class PostingDetailsReaderTest extends TestCase
{
    public function testCompleteDetails(): void
    {
        $reader = new DraftReader();

        $details = (new PostingDetailsReader($reader))->required('posting', PostingDrafts::details());

        static::assertSame([], ViolationSummary::of($reader->result()));
        static::assertNotNull($details);
        static::assertSame('2690549588157', $details->worker()?->nir()->value());
        static::assertSame('2026-01-01', $details->startDate()?->toString());
        static::assertSame('2026-06-30', $details->endDate()?->toString());
        $telework = $details->telework();
        static::assertNotNull($telework);
        static::assertTrue($telework->teleworking());
        static::assertTrue($telework->workerAgreement());
        static::assertSame(Country::Italie, $telework->country());
        static::assertSame(2, $telework->daysPerMonth());
        $host = $details->hostAddress();
        static::assertNotNull($host);
        static::assertFalse($host->usualAddress());
        static::assertSame('1 rue Exemple', $host->address()?->line()->value());
        static::assertSame(Answer::Yes, $details->sameActivitySector());
        static::assertSame(Answer::NotApplicable, $details->durableInfrastructure());
        $substantial = $details->substantialActivity();
        static::assertNotNull($substantial);
        static::assertSame(Answer::No, $substantial->answer());
        static::assertSame(Country::Italie, $substantial->countryOfInterest());
        static::assertSame(ActivityCriterion::TempsTravail, $substantial->criterion());
        static::assertSame(Country::Italie, $details->declaredActivity()?->country());
        static::assertCount(2, $details->currentActivities());
        static::assertSame('Commentaire', $details->comment()?->value());
    }

    public function testEveryFieldIsOptional(): void
    {
        $reader = new DraftReader();

        $details = (new PostingDetailsReader($reader))->required('posting', PostingDrafts::emptyDetails());

        static::assertTrue($reader->result()->isValid());
        static::assertEquals(PostingDetails::of(null, null, null, null, null, null, null, null, null, [], null), $details);
        static::assertNull((new PostingDetailsReader($reader))->required('a.posting', null));
        static::assertSame(['a.posting field.required'], ViolationSummary::of($reader->result()));
    }

    /**
     * @return iterable<string, array{TeleworkDraft, list<string>}>
     */
    public static function provideInvalidTelework(): iterable
    {
        yield 'teleworking without days' => [new TeleworkDraft(true, null, null, null), ['posting.telework.daysPerMonth field.required']];
        yield 'teleworking with 0 days' => [new TeleworkDraft(true, null, null, 0), ['posting.telework.daysPerMonth days_per_month.out_of_range']];
        yield 'teleworking with 32 days' => [new TeleworkDraft(true, null, null, 32), ['posting.telework.daysPerMonth days_per_month.out_of_range']];
        yield 'not teleworking with 32 days' => [new TeleworkDraft(false, null, null, 32), ['posting.telework.daysPerMonth days_per_month.out_of_range']];
        yield 'missing teleworking' => [new TeleworkDraft(null, true, null, null), ['posting.telework.teleworking field.required']];
        yield 'missing teleworking, days still checked' => [
            new TeleworkDraft(null, null, 'X', 0),
            ['posting.telework.teleworking field.required', 'posting.telework.country country.unknown', 'posting.telework.daysPerMonth days_per_month.out_of_range'],
        ];
        yield 'teleworking, invalid country, no days' => [
            new TeleworkDraft(true, null, '', null),
            ['posting.telework.country country.unknown', 'posting.telework.daysPerMonth field.required'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidTelework')]
    public function testInvalidTelework(TeleworkDraft $telework, array $expected): void
    {
        $reader = new DraftReader();
        $draft = new PostingDetailsDraft(null, null, null, $telework, null, null, null, null, null, [], null);

        static::assertNull((new PostingDetailsReader($reader))->required('posting', $draft)?->telework());
        static::assertSame($expected, ViolationSummary::of($reader->result()));
    }

    public function testValidTeleworkBounds(): void
    {
        $reader = new DraftReader();
        $details = new PostingDetailsReader($reader);
        $notTeleworking = new PostingDetailsDraft(null, null, null, new TeleworkDraft(false, false, 'MALTE', 31), null, null, null, null, null, [], null);
        $minimum = new PostingDetailsDraft(null, null, null, new TeleworkDraft(true, null, null, 1), null, null, null, null, null, [], null);
        $withoutDays = new PostingDetailsDraft(null, null, null, new TeleworkDraft(false, null, null, null), null, null, null, null, null, [], null);

        $telework = $details->required('posting', $notTeleworking)?->telework();

        static::assertNotNull($telework);
        static::assertFalse($telework->teleworking());
        static::assertFalse($telework->workerAgreement());
        static::assertSame(Country::Malte, $telework->country());
        static::assertSame(31, $telework->daysPerMonth());
        static::assertSame(1, $details->required('posting', $minimum)?->telework()?->daysPerMonth());
        static::assertFalse($details->required('posting', $withoutDays)?->telework()?->teleworking());
        static::assertTrue($reader->result()->isValid());
    }

    public function testHostAddress(): void
    {
        $reader = new DraftReader();
        $details = new PostingDetailsReader($reader);
        $invalid = new AddressDraft(null, null, null, null, null);
        $usual = new PostingDetailsDraft(null, null, null, null, new HostAddressDraft(true, $invalid), null, null, null, null, [], null);
        $missing = new PostingDetailsDraft(null, null, null, null, new HostAddressDraft(null, $invalid), null, null, null, null, [], null);
        $elsewhere = new PostingDetailsDraft(null, null, null, null, new HostAddressDraft(false, null), null, null, null, null, [], null);

        $usualHost = $details->required('posting', $usual)?->hostAddress();
        static::assertTrue($reader->result()->isValid(), 'ignored by the contract: no violation');
        static::assertNotNull($usualHost);
        static::assertTrue($usualHost->usualAddress());
        static::assertNull($usualHost->address());
        static::assertNull($details->required('posting', $missing)?->hostAddress());
        static::assertSame(
            ['posting.hostAddress.usualAddress field.required', 'posting.hostAddress.address.line field.required', 'posting.hostAddress.address.country field.required'],
            ViolationSummary::of($reader->result()),
        );
        $elsewhereHost = $details->required('posting', $elsewhere)?->hostAddress();
        static::assertNotNull($elsewhereHost);
        static::assertFalse($elsewhereHost->usualAddress());
        static::assertNull($elsewhereHost->address());
    }

    public function testSubstantialActivityAndInvalidValues(): void
    {
        $reader = new DraftReader();
        $worker = new WorkerDraft(null, null, '', null, '', '1969-05-31', null, null, 'FRANCE', null, null, null, null);
        $draft = new PostingDetailsDraft($worker, '2026-13-01', '01/01/2026', null, null, 'oui', 'Non', new SubstantialActivityDraft(null, 'X', 'CA '), null, [], '');

        $details = (new PostingDetailsReader($reader))->required('posting', $draft);

        static::assertNull($details?->worker());
        static::assertNull($details?->substantialActivity());
        static::assertSame('', $details?->comment()?->value());
        static::assertSame(
            [
                'posting.worker.nir field.required',
                'posting.startDate calendar_date.invalid_date',
                'posting.endDate calendar_date.invalid_format',
                'posting.sameActivitySector answer.unknown',
                'posting.durableInfrastructure answer.unknown',
                'posting.substantialActivity.answer field.required',
                'posting.substantialActivity.countryOfInterest country.unknown',
                'posting.substantialActivity.criterion activity_criterion.unknown',
            ],
            ViolationSummary::of($reader->result()),
        );
    }

    public function testMinimalSubstantialActivity(): void
    {
        $reader = new DraftReader();
        $draft = new PostingDetailsDraft(null, null, null, null, null, null, null, new SubstantialActivityDraft('NC', null, null), null, [], null);

        $substantial = (new PostingDetailsReader($reader))->required('posting', $draft)?->substantialActivity();

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($substantial);
        static::assertSame(Answer::NotApplicable, $substantial->answer());
        static::assertNull($substantial->countryOfInterest());
        static::assertNull($substantial->criterion());
    }

    public function testActivitiesAreReportedAtTheirIndex(): void
    {
        $reader = new DraftReader();
        $invalid = new ActivityDeclarationDraft('SALARIE', null, PostingDrafts::company(), null, null, null, null, null, null, null, null);
        $first = PostingDrafts::activity();
        $last = new ActivityDeclarationDraft('ETUDIANT', 'MALTE', PostingDrafts::company(), null, null, null, null, null, null, null, null);
        $draft = new PostingDetailsDraft(null, null, null, null, null, null, null, null, $invalid, [$first, $invalid, $last, $invalid], null);

        $details = (new PostingDetailsReader($reader))->required('posting', $draft);

        static::assertNull($details?->declaredActivity());
        static::assertSame(
            [
                'posting.declaredActivity.country field.required',
                'posting.currentActivities[1].country field.required',
                'posting.currentActivities[3].country field.required',
            ],
            ViolationSummary::of($reader->result()),
        );
        $activities = $details?->currentActivities() ?? [];
        static::assertSame([0, 1], array_keys($activities));
        static::assertSame(Country::Italie, $activities[0]->country());
        static::assertSame(Country::Malte, $activities[1]->country());
    }
}

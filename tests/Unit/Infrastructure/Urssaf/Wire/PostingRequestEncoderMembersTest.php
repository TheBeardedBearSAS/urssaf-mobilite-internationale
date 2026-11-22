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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;

final class PostingRequestEncoderMembersTest extends TestCase
{
    public function testAnIgnoredAddressIsNeverEmitted(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(HostAddress::usualHome(), null, null, []),
        ));

        static::assertSame(['adresseHabituelle' => true], EncodedBody::section(EncodedBody::demande($body), 'adresseAccueil'));
        static::assertSame(
            '{"raisonSociale":"Declaring","adresseConnue":false,"email":"declaring@example.invalid"}',
            JsonEncoder::encode(EncodedBody::section(EncodedBody::section($body, 'declaration'), 'entrepriseDeclarant')),
        );
    }

    public function testAHostAddressElsewhereWithoutAddressIsJustTheFlag(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(HostAddress::elsewhere(null), null, null, []),
        ));

        static::assertSame(['adresseHabituelle' => false], EncodedBody::section(EncodedBody::demande($body), 'adresseAccueil'));
    }

    public function testAnAddressWithOnlyItsRequiredMembersOmitsTheOthers(): void
    {
        $address = Address::of(Label::fromString('Line only'), null, null, null, Country::Italie);
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(HostAddress::elsewhere($address), null, null, []),
        ));

        static::assertSame(
            ['adresseHabituelle' => false, 'adresse' => ['adresse' => 'Line only', 'pays' => 'ITALIE']],
            EncodedBody::section(EncodedBody::demande($body), 'adresseAccueil'),
        );
    }

    public function testTeleworkKeepsEveryFlagIncludingFalse(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(null, Telework::withoutTelework(false, Country::Italie, null), null, []),
        ));

        static::assertSame(
            ['teletravail' => false, 'accordSalarie' => false, 'pays' => 'ITALIE'],
            EncodedBody::section(EncodedBody::demande($body), 'teletravail'),
        );
    }

    public function testAWorkerAndASubstantialActivityWithOnlyTheirRequiredMembers(): void
    {
        $worker = Worker::of(
            Nir::fromString('0000000000000'),
            null,
            Label::fromString('Usage Test'),
            null,
            Label::fromString('First Test'),
            CalendarDate::fromString('2000-01-31'),
            null,
            null,
            Country::Belgique,
            null,
            null,
            null,
            null,
        );
        $details = PostingDetails::of(
            $worker,
            null,
            null,
            Telework::withoutTelework(null, null, null),
            null,
            null,
            null,
            SubstantialActivity::of(Answer::Yes, null, null),
            null,
            [],
            null,
        );

        $demande = EncodedBody::demande((new PostingRequestEncoder())->encode(SyntheticPostings::withDetails($details)));

        static::assertSame(
            [
                'personne' => [
                    'nir' => '0000000000000',
                    'nomUsage' => 'Usage Test',
                    'prenom' => 'First Test',
                    'dateNaissance' => '2000-01-31',
                    'nationalite' => 'BELGIQUE',
                ],
                'teletravail' => ['teletravail' => false],
                'activiteSubstentielle' => ['activiteSubstentielle' => 'OUI'],
            ],
            $demande,
        );
    }

    public function testActivityDetailsWithoutAnyFieldAreOmitted(): void
    {
        $declared = SyntheticActivities::bare('Declared', ActivityDetails::of(null, null, null, null, null, null));
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(self::posting(null, null, $declared, [])));

        static::assertArrayNotHasKey(
            'activiteDeclareeCplt',
            EncodedBody::section(EncodedBody::demande($body), 'activitesDeclarees'),
        );
    }

    public function testActivityDetailsKeepOnlyTheFieldsThatAreSet(): void
    {
        $declared = SyntheticActivities::bare('Declared', ActivityDetails::of(null, false, null, null, null, true));
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(self::posting(null, null, $declared, [])));

        static::assertSame(
            ['affiliationSecuriteSociale' => false, 'contratTravailEstEtranger' => true],
            EncodedBody::section(EncodedBody::section(EncodedBody::demande($body), 'activitesDeclarees'), 'activiteDeclareeCplt'),
        );
    }

    public function testDeclaredActivityIsOneObjectAndCurrentActivitiesAreAList(): void
    {
        $declared = SyntheticActivities::bare('Declared', null);
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(null, null, $declared, [$declared]),
        ));
        $demande = EncodedBody::demande($body);

        static::assertStringStartsWith('{"nature":', JsonEncoder::encode(EncodedBody::section($demande, 'activitesDeclarees')));
        static::assertStringStartsWith('[{"nature":', json_encode($demande['activitesActuelles'], JSON_THROW_ON_ERROR));
    }

    public function testAnEmptyListOfCurrentActivitiesOmitsTheKey(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::withDetails(
            self::posting(null, null, SyntheticActivities::bare('Declared', null), []),
        ));

        static::assertArrayNotHasKey('activitesActuelles', EncodedBody::demande($body));
        static::assertArrayHasKey('activitesDeclarees', EncodedBody::demande($body));
    }

    /**
     * @param list<ActivityDeclaration> $current
     */
    private static function posting(
        ?HostAddress $host,
        ?Telework $telework,
        ?ActivityDeclaration $declared,
        array $current,
    ): PostingDetails {
        return PostingDetails::of(null, null, null, $telework, $host, null, null, null, $declared, $current, null);
    }
}

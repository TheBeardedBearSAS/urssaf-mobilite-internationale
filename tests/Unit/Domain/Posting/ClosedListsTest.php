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

use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityCriterion;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityType;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;

final class ClosedListsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<BackedEnum>, string, list<string>}>
     */
    public static function provideContractSchemas(): iterable
    {
        yield 'Answer' => [Answer::cases(), 'Answer', ['NC', 'OUI', 'NON']];
        yield 'ActivityCriterion' => [ActivityCriterion::cases(), 'CritereDActivite', ['CA', 'TempsTravail', 'Prestations', 'Remuneration']];
        yield 'ActivityType' => [ActivityType::cases(), 'type', ['AUTRE']];
        yield 'ActivityNature' => [
            ActivityNature::cases(),
            'Nature',
            [
                'TRAVAILLEUR_INDEPENDANT',
                'SALARIE',
                'FONCTIONNAIRE',
                'MEMBRE_D_EQUIPAGE',
                'AGENT_CONTRACTUEL',
                'SALARIE_INTERMITTENT',
                'DEMANDEUR_EMPLOI',
                'ARTISTE_AUTEUR',
                'SALARIE_NON_SALARIE',
                'NON_SALARIE',
                'PENSIONNE',
                'ETUDIANT',
                'AUTRE',
                'TOUS',
            ],
        ];
    }

    /**
     * @param list<BackedEnum> $cases
     * @param list<string>     $expected
     */
    #[DataProvider('provideContractSchemas')]
    public function testValuesAreTheWireValuesInOrder(array $cases, string $schema, array $expected): void
    {
        static::assertSame($expected, array_map(static fn(BackedEnum $case): string => (string) $case->value, $cases), $schema);
    }

    public function testAnswerIsNeverABoolean(): void
    {
        static::assertSame('NC', Answer::NotApplicable->value);
        static::assertSame('OUI', Answer::Yes->value);
        static::assertSame('NON', Answer::No->value);
        static::assertNull(Answer::tryFrom('true'));
        static::assertNull(Answer::tryFrom(''));
        static::assertNull(Answer::tryFrom('oui'));
    }

    public function testActivityNatureCaseNames(): void
    {
        static::assertSame('MEMBRE_D_EQUIPAGE', ActivityNature::MembreDEquipage->value);
        static::assertSame('SALARIE_INTERMITTENT', ActivityNature::SalarieIntermittent->value);
        static::assertSame(ActivityNature::Tous, ActivityNature::from('TOUS'));
    }

    public function testCriterionAndTypeCaseNames(): void
    {
        static::assertSame(ActivityCriterion::CA, ActivityCriterion::from('CA'));
        static::assertSame(ActivityCriterion::TempsTravail, ActivityCriterion::from('TempsTravail'));
        static::assertSame(ActivityType::Autre, ActivityType::from('AUTRE'));
    }
}

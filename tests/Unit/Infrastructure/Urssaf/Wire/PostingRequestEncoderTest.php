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
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;

final class PostingRequestEncoderTest extends TestCase
{
    public function testItEncodesEveryMemberWithTheVerbatimFrenchNames(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::full());

        self::assertWire([
            'declarant' => [
                'nom' => 'Declarant Test',
                'email' => 'declarant@example.invalid',
                'tel1' => '0000000001',
                'tel2' => '0000000002',
            ],
            'declaration' => [
                'entrepriseDeclarant' => self::company('Declaring', true),
                'referenceEntreprise' => [
                    'siren' => '000000000',
                    'nic' => '00001',
                    'numeroCompteExterne' => '000000000000000001',
                ],
                'signature' => ['autorisationDonneesPerso' => true, 'declarationHonneur' => false],
                'demande' => JsonObject::of([
                    'personne' => [
                        'nir' => '0000000000000',
                        'cleNir' => '00',
                        'nomUsage' => 'Usage Test',
                        'nomNaissance' => 'Birth Test',
                        'prenom' => 'First Test',
                        'dateNaissance' => '2000-01-31',
                        'lieuNaissance' => 'Birthplace Test',
                        'paysNaissance' => 'FRANCE',
                        'nationalite' => 'BELGIQUE',
                        'adresse' => self::address('Worker street'),
                        'email' => 'worker@example.invalid',
                        'tel1' => '0000000003',
                        'tel2' => '0000000004',
                    ],
                    'dateDebut' => '2026-02-01',
                    'dateFin' => '2026-08-31',
                    'teletravail' => ['teletravail' => true, 'accordSalarie' => true, 'pays' => 'AUTRICHE', 'nombreJours' => 3],
                    'adresseAccueil' => ['adresseHabituelle' => false, 'adresse' => self::address('Host street')],
                    'memeDomaineActivite' => 'OUI',
                    'infraPerenne' => 'NC',
                    'activiteSubstentielle' => [
                        'activiteSubstentielle' => 'NON',
                        'paysInteret' => 'POLOGNE',
                        'critereDActivite' => 'TempsTravail',
                    ],
                    'activitesDeclarees' => self::completeActivity('Declared', [true, false, true, false, true, false]),
                    'activitesActuelles' => [
                        self::completeActivity('Current one', [false, true, false, true, false, true]),
                        [
                            'nature' => 'SALARIE',
                            'pays' => 'ALLEMAGNE',
                            'entreprise' => ['raisonSociale' => 'Current two', 'adresseConnue' => false, 'email' => 'current-two@example.invalid'],
                        ],
                    ],
                    'commentaire' => 'Free comment',
                ]),
            ],
        ], $body);
    }

    public function testItOmitsEveryOptionalMemberInsteadOfSendingNull(): void
    {
        $body = (new PostingRequestEncoder())->encode(SyntheticPostings::minimal());

        self::assertWire([
            'declarant' => [
                'nom' => 'Declarant Test',
                'email' => 'declarant@example.invalid',
                'tel1' => '0000000001',
            ],
            'declaration' => [
                'entrepriseDeclarant' => ['raisonSociale' => 'Declaring', 'adresseConnue' => false, 'email' => 'declaring@example.invalid'],
                'referenceEntreprise' => ['siren' => '000000000', 'nic' => '00001'],
                'signature' => ['autorisationDonneesPerso' => false, 'declarationHonneur' => true],
                'demande' => JsonObject::of([]),
            ],
        ], $body);
        static::assertStringContainsString('"demande":{}', JsonEncoder::encode($body));
        static::assertStringNotContainsString('null', JsonEncoder::encode($body));
    }

    public function testItKeepsTheContractSpellingOfActiviteSubstentielle(): void
    {
        $json = JsonEncoder::encode((new PostingRequestEncoder())->encode(SyntheticPostings::full()));

        static::assertStringContainsString('"activiteSubstentielle":{"activiteSubstentielle":"NON"', $json);
        static::assertStringNotContainsString('activiteSubstantielle', $json);
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private static function assertWire(array $expected, array $actual): void
    {
        static::assertSame(JsonEncoder::encode($expected), JsonEncoder::encode($actual));
    }

    /**
     * @return array<string, mixed>
     */
    private static function address(string $line): array
    {
        return [
            'adresse' => $line,
            'adresseCplt' => $line . ' complement',
            'ville' => $line . ' city',
            'codePostal' => '00000',
            'pays' => 'ITALIE',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function company(string $name, bool $known): array
    {
        return [
            'raisonSociale' => $name,
            'adresse' => self::address($name . ' street'),
            'adresseConnue' => $known,
            'email' => SyntheticParties::slug($name) . '@example.invalid',
        ];
    }

    /**
     * @param array{bool, bool, bool, bool, bool, bool} $details
     *
     * @return array<string, mixed>
     */
    private static function completeActivity(string $name, array $details): array
    {
        return [
            'nature' => 'SALARIE',
            'pays' => 'ALLEMAGNE',
            'entreprise' => self::company($name, true),
            'type' => 'AUTRE',
            'natureAutre' => $name . ' other nature',
            'natureBaseAffectation' => 'ESPAGNE',
            'activiteFrancaise' => false,
            'activiteSup25' => true,
            'factureEmise' => false,
            'contratSigne' => true,
            'activiteDeclareeCplt' => [
                'salarieRemplacant' => $details[0],
                'affiliationSecuriteSociale' => $details[1],
                'remunerationModifiable' => $details[2],
                'licenciementSalarie' => $details[3],
                'sanctionsDisciplinairesSalarie' => $details[4],
                'contratTravailEstEtranger' => $details[5],
            ],
        ];
    }
}

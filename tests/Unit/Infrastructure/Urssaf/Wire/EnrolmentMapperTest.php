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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\EnrolmentMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class EnrolmentMapperTest extends TestCase
{
    private const ID = '0b9f3f0e-0000-4000-8000-000000000001';

    public function testItBuildsTheRequestBodyWithTheNic(): void
    {
        $body = (new EnrolmentMapper())->toRequestBody(Siren::fromString('000000000'), Nic::fromString('00001'), EnrolmentCategory::Rg);

        static::assertSame(['siren' => '000000000', 'nic' => '00001', 'categorie' => 'RG'], $body);
    }

    public function testItOmitsTheNicInsteadOfSendingNull(): void
    {
        $body = (new EnrolmentMapper())->toRequestBody(Siren::fromString('000000000'), null, EnrolmentCategory::Autre);

        static::assertSame(['siren' => '000000000', 'categorie' => 'AUTRE'], $body);
        static::assertArrayNotHasKey('nic', $body);
    }

    public function testItReadsACompleteEnrolment(): void
    {
        $enrolment = (new EnrolmentMapper())->fromPayload(JsonDecoder::decode(
            '{"idEnrolement": "0B9F3F0E-0000-4000-8000-000000000001", "siren": "000000000", "nic": "00001", "categorie": "RGACT",'
            . ' "status": "ACCEPTE", "dateDemande": "2026-01-02T04:04:05+0100", "dateMiseAJour": "2026-01-03T03:04:05Z"}',
        ));

        static::assertSame(self::ID, $enrolment->id()->value());
        static::assertSame('000000000', $enrolment->siren()?->value());
        static::assertSame('00001', $enrolment->nic()?->value());
        static::assertSame('RGACT', $enrolment->rawCategory());
        static::assertSame(EnrolmentCategory::Rgact, $enrolment->category());
        static::assertSame('ACCEPTE', $enrolment->rawStatus());
        static::assertSame(EnrolmentStatus::Accepte, $enrolment->status());
        static::assertSame('2026-01-02T03:04:05Z', $enrolment->requestedAt()?->toIso8601());
        static::assertSame('2026-01-03T03:04:05Z', $enrolment->updatedAt()?->toIso8601());
    }

    public function testNoFieldButTheIdentifierIsRequired(): void
    {
        $enrolment = (new EnrolmentMapper())->fromPayload(JsonDecoder::decode('{"idEnrolement": "' . self::ID . '"}'));

        static::assertSame(self::ID, $enrolment->id()->value());
        static::assertNull($enrolment->siren());
        static::assertNull($enrolment->nic());
        static::assertNull($enrolment->rawCategory());
        static::assertNull($enrolment->rawStatus());
        static::assertNull($enrolment->requestedAt());
        static::assertNull($enrolment->updatedAt());
    }

    public function testUnknownCategoryAndStatusAreKeptRaw(): void
    {
        $enrolment = (new EnrolmentMapper())->fromPayload(JsonDecoder::decode(
            '{"idEnrolement": "' . self::ID . '", "categorie": "NOUVEAU", "status": "SUSPENDU"}',
        ));

        static::assertSame('NOUVEAU', $enrolment->rawCategory());
        static::assertNull($enrolment->category());
        static::assertSame('SUSPENDU', $enrolment->rawStatus());
        static::assertNull($enrolment->status());
    }

    #[DataProvider('unreadableEnrolments')]
    public function testItRefusesAMissingIdentifierAndPresentButMalformedValues(string $json, string $reason): void
    {
        try {
            (new EnrolmentMapper())->fromPayload(JsonDecoder::decode($json));
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame($reason, $exception->reason());
            static::assertStringNotContainsString('SECRET', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadableEnrolments(): iterable
    {
        $id = '"idEnrolement": "' . self::ID . '"';
        $malformed = 'a field of the enrolment is malformed';

        yield 'no id' => ['{"siren": "000000000"}', 'field "idEnrolement" is missing or empty'];
        yield 'id not a uuid' => ['{"idEnrolement": "SECRET"}', $malformed];
        yield 'siren too short' => ['{' . $id . ', "siren": "SECRET"}', $malformed];
        yield 'empty siren' => ['{' . $id . ', "siren": ""}', $malformed];
        yield 'nic too long' => ['{' . $id . ', "nic": "SECRET1"}', $malformed];
        yield 'bad requested date' => ['{' . $id . ', "dateDemande": "SECRET"}', 'a date-time is not valid ISO 8601 with an offset'];
        yield 'bad update date' => ['{' . $id . ', "dateMiseAJour": "2026-01-02"}', 'a date-time is not valid ISO 8601 with an offset'];
    }

    public function testItReadsAList(): void
    {
        $enrolments = (new EnrolmentMapper())->listFromPayload(JsonDecoder::decode(
            '[{"idEnrolement": "' . self::ID . '", "status": "ENCOURS"}, {"idEnrolement": "0b9f3f0e-0000-4000-8000-000000000002"}]',
        ));

        static::assertCount(2, $enrolments);
        static::assertSame(self::ID, $enrolments[0]->id()->value());
        static::assertSame('ENCOURS', $enrolments[0]->rawStatus());
        static::assertSame('0b9f3f0e-0000-4000-8000-000000000002', $enrolments[1]->id()->value());
        static::assertSame([], (new EnrolmentMapper())->listFromPayload(JsonDecoder::decode('[]')));
    }

    #[DataProvider('unreadableLists')]
    public function testItRefusesAListThatIsNotMadeOfReadableEnrolments(string $json): void
    {
        $this->expectException(UnreadablePayload::class);
        (new EnrolmentMapper())->listFromPayload(JsonDecoder::decode($json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableLists(): iterable
    {
        yield 'object' => ['{"idEnrolement": "' . self::ID . '"}'];
        yield 'scalars' => ['["x"]'];
        yield 'one without id' => ['[{"idEnrolement": "' . self::ID . '"}, {"siren": "000000000"}]'];
    }
}

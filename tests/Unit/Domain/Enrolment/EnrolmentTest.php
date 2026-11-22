<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Enrolment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

final class EnrolmentTest extends TestCase
{
    private const ID = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

    public function testFullEnrolment(): void
    {
        $requestedAt = Instant::fromIso8601('2025-01-02T03:04:05Z');
        $updatedAt = Instant::fromIso8601('2025-01-03T03:04:05Z');

        $enrolment = Enrolment::of(
            EnrolmentId::fromString(self::ID),
            Siren::fromString('123456789'),
            Nic::fromString('12345'),
            'AE',
            'ACCEPTE',
            $requestedAt,
            $updatedAt,
        );

        static::assertSame(self::ID, $enrolment->id()->value());
        static::assertSame('123456789', $enrolment->siren()?->value());
        static::assertSame('12345', $enrolment->nic()?->value());
        static::assertSame('AE', $enrolment->rawCategory());
        static::assertSame(EnrolmentCategory::Ae, $enrolment->category());
        static::assertSame('ACCEPTE', $enrolment->rawStatus());
        static::assertSame(EnrolmentStatus::Accepte, $enrolment->status());
        static::assertTrue($enrolment->isAccepted());
        static::assertSame($requestedAt, $enrolment->requestedAt());
        static::assertSame($updatedAt, $enrolment->updatedAt());
    }

    public function testOnlyTheIdentifierIsRequired(): void
    {
        $enrolment = Enrolment::of(EnrolmentId::fromString(self::ID), null, null, null, null, null, null);

        static::assertNull($enrolment->siren());
        static::assertNull($enrolment->nic());
        static::assertNull($enrolment->rawCategory());
        static::assertNull($enrolment->category());
        static::assertNull($enrolment->rawStatus());
        static::assertNull($enrolment->status());
        static::assertFalse($enrolment->isAccepted());
        static::assertNull($enrolment->requestedAt());
        static::assertNull($enrolment->updatedAt());
    }

    public function testUnknownValuesAreKeptRaw(): void
    {
        $enrolment = Enrolment::of(EnrolmentId::fromString(self::ID), null, null, 'NOUVELLE', 'EN_PAUSE', null, null);

        static::assertSame('NOUVELLE', $enrolment->rawCategory());
        static::assertNull($enrolment->category());
        static::assertSame('EN_PAUSE', $enrolment->rawStatus());
        static::assertNull($enrolment->status());
        static::assertFalse($enrolment->isAccepted());
    }

    public function testNotAcceptedStatus(): void
    {
        $enrolment = Enrolment::of(EnrolmentId::fromString(self::ID), null, null, null, 'ENCOURS', null, null);

        static::assertSame(EnrolmentStatus::Encours, $enrolment->status());
        static::assertFalse($enrolment->isAccepted());
    }

    /**
     * @return iterable<string, array{string, ?EnrolmentCategory}>
     */
    public static function provideCategories(): iterable
    {
        yield 'AA' => ['AA', EnrolmentCategory::Aa];
        yield 'AE' => ['AE', EnrolmentCategory::Ae];
        yield 'RG' => ['RG', EnrolmentCategory::Rg];
        yield 'ACT' => ['ACT', EnrolmentCategory::Act];
        yield 'RGACT' => ['RGACT', EnrolmentCategory::Rgact];
        yield 'TIAC' => ['TIAC', EnrolmentCategory::Tiac];
        yield 'TIPL' => ['TIPL', EnrolmentCategory::Tipl];
        yield 'TIAE' => ['TIAE', EnrolmentCategory::Tiae];
        yield 'TIPM' => ['TIPM', EnrolmentCategory::Tipm];
        yield 'AUTRE' => ['AUTRE', EnrolmentCategory::Autre];
        yield 'unknown' => ['XX', null];
        yield 'empty' => ['', null];
        yield 'lower case' => ['ae', null];
    }

    #[DataProvider('provideCategories')]
    public function testCategoryFromCode(string $code, ?EnrolmentCategory $expected): void
    {
        static::assertSame($expected, EnrolmentCategory::fromCode($code));
        static::assertCount(10, EnrolmentCategory::cases());
    }

    /**
     * @return iterable<string, array{string, ?EnrolmentStatus}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'ACCEPTE' => ['ACCEPTE', EnrolmentStatus::Accepte];
        yield 'ENCOURS' => ['ENCOURS', EnrolmentStatus::Encours];
        yield 'REFUSE' => ['REFUSE', EnrolmentStatus::Refuse];
        yield 'SUPPRIME' => ['SUPPRIME', EnrolmentStatus::Supprime];
        yield 'EN_COURS (unused Etat enum)' => ['EN_COURS', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('provideStatuses')]
    public function testStatusFromCode(string $code, ?EnrolmentStatus $expected): void
    {
        static::assertSame($expected, EnrolmentStatus::fromCode($code));
        static::assertCount(4, EnrolmentStatus::cases());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Testing;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedBinaryStream;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;

/**
 * @mago-expect lint:too-many-methods One test per behaviour of the simulated platform; splitting them would only scatter the shared fixtures.
 */
#[CoversClass(SimulatedMobilityGateway::class)]
#[CoversClass(SimulatedCertificate::class)]
final class SimulatedMobilityGatewayTest extends TestCase
{
    private const FIRST_ENROLMENT = '00000000-0000-4000-8000-000000000001';
    private const SECOND_ENROLMENT = '00000000-0000-4000-8000-000000000002';
    private const FIRST_DOSSIER = '00000000-0000-4000-9000-000000000001';
    private const SECOND_DOSSIER = '00000000-0000-4000-9000-000000000002';

    public function testItRefusesTheProductionEnvironment(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The simulated gateway is meant for tests and cannot be used with the production environment.');

        SimulatedMobilityGateway::forEnvironment(Environment::Production);
    }

    public function testItAcceptsTheSandboxAndNoEnvironment(): void
    {
        $tenant = EnrolmentSamples::tenant();

        foreach ([SimulatedMobilityGateway::create(), SimulatedMobilityGateway::forEnvironment(Environment::Sandbox)] as $gateway) {
            static::assertSame([], $gateway->calls());
            static::assertSame(self::FIRST_ENROLMENT, $gateway->requestEnrolment($tenant, Siren::fromString('000000000'), null, EnrolmentCategory::Rg)->id()->value());
        }
    }

    public function testANewEnrolmentIsPendingWithDeterministicIdentifiers(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();

        $first = $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), Nic::fromString('00001'), EnrolmentCategory::Rg);
        $second = $gateway->requestEnrolment($tenant, Siren::fromString('000000002'), null, EnrolmentCategory::Autre);

        static::assertSame(self::FIRST_ENROLMENT, $first->id()->value());
        static::assertSame(self::SECOND_ENROLMENT, $second->id()->value());
        static::assertSame('000000001', $first->siren()?->value());
        static::assertSame('00001', $first->nic()?->value());
        static::assertNull($second->nic());
        static::assertSame('RG', $first->rawCategory());
        static::assertSame('AUTRE', $second->rawCategory());
        static::assertSame('ENCOURS', $first->rawStatus());
        static::assertFalse($first->isAccepted());
        static::assertSame('2026-01-01T00:00:00Z', $first->requestedAt()?->toIso8601());
        static::assertSame('2026-01-01T00:00:00Z', $first->updatedAt()?->toIso8601());
        static::assertSame($first, $gateway->getEnrolment($tenant, $first->id()));
    }

    public function testTheEnrolmentOfAnAlreadyEnrolledSirenIsRejected(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();
        $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), null, EnrolmentCategory::Rg);

        try {
            $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), null, EnrolmentCategory::Rg);
            static::fail('The duplicate must be rejected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertSame(Operation::RequestEnrolment, $exception->operation());
        }
        $other = $gateway->requestEnrolment($tenant, Siren::fromString('000000002'), null, EnrolmentCategory::Rg);
        static::assertSame(self::SECOND_ENROLMENT, $other->id()->value());
    }

    public function testADeletedEnrolmentLetsTheSirenEnrolAgain(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();
        $first = $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), null, EnrolmentCategory::Rg);

        $gateway->deleteEnrolment($tenant, $first->id());
        $again = $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), null, EnrolmentCategory::Rg);

        static::assertSame(EnrolmentStatus::Supprime, $gateway->getEnrolment($tenant, $first->id())->status());
        static::assertSame(self::SECOND_ENROLMENT, $again->id()->value());
        static::assertSame(EnrolmentStatus::Encours, $again->status());
    }

    public function testTheTestSettlesTheEnrolment(): void
    {
        $clock = new SteppingClock(1_000_000);
        $gateway = new SimulatedMobilityGateway($clock);
        $tenant = EnrolmentSamples::tenant();
        $accepted = $gateway->requestEnrolment($tenant, Siren::fromString('000000001'), Nic::fromString('00001'), EnrolmentCategory::Rg);
        $refused = $gateway->requestEnrolment($tenant, Siren::fromString('000000002'), null, EnrolmentCategory::Ae);

        $gateway->acceptEnrolment($accepted->id());
        $gateway->refuseEnrolment($refused->id());

        $read = $gateway->getEnrolment($tenant, $accepted->id());
        static::assertTrue($read->isAccepted());
        static::assertSame('ACCEPTE', $read->rawStatus());
        static::assertSame('000000001', $read->siren()?->value());
        static::assertSame('00001', $read->nic()?->value());
        static::assertSame('RG', $read->rawCategory());
        static::assertSame(self::FIRST_ENROLMENT, $read->id()->value());
        static::assertSame('2024-01-01T10:00:00Z', $read->requestedAt()?->toIso8601());
        static::assertSame('2024-01-01T10:00:02Z', $read->updatedAt()?->toIso8601());
        static::assertSame(EnrolmentStatus::Refuse, $gateway->getEnrolment($tenant, $refused->id())->status());
        static::assertSame('2024-01-01T10:00:03Z', $gateway->getEnrolment($tenant, $refused->id())->updatedAt()?->toIso8601());
    }

    public function testSettlingAnUnknownEnrolmentIsATestMistake(): void
    {
        $gateway = SimulatedMobilityGateway::create();

        try {
            $gateway->acceptEnrolment(EnrolmentSamples::enrolmentId());
            static::fail('An unknown enrolment must be refused.');
        } catch (LogicException $exception) {
            static::assertSame('Unknown simulated enrolment.', $exception->getMessage());
        }
        try {
            $gateway->refuseEnrolment(EnrolmentSamples::enrolmentId());
            static::fail('An unknown enrolment must be refused.');
        } catch (LogicException $exception) {
            static::assertSame('Unknown simulated enrolment.', $exception->getMessage());
        }
    }

    public function testAnUnknownEnrolmentIsNotFound(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();

        try {
            $gateway->getEnrolment($tenant, EnrolmentSamples::enrolmentId());
            static::fail('A 404 was expected.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame(Operation::GetEnrolment, $exception->operation());
            static::assertSame(404, $exception->httpStatus());
        }
        try {
            $gateway->deleteEnrolment($tenant, EnrolmentSamples::enrolmentId());
            static::fail('A 404 was expected.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame(Operation::DeleteEnrolment, $exception->operation());
            static::assertSame(404, $exception->httpStatus());
        }
    }

    public function testEnrolmentsAreListedByPagesInCreationOrder(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();
        foreach (['000000001', '000000002', '000000003'] as $siren) {
            $gateway->requestEnrolment($tenant, Siren::fromString($siren), null, EnrolmentCategory::Rg);
        }

        $full = $gateway->listEnrolments($tenant, 0, 3, null);
        $firstPage = $gateway->listEnrolments($tenant, 0, 2, 'ignored');
        $lastPage = $gateway->listEnrolments($tenant, 2, 2, null);
        $past = $gateway->listEnrolments($tenant, 3, 2, null);

        static::assertCount(3, $full->enrolments());
        static::assertFalse($full->isLastPage());
        static::assertSame(
            ['000000001', '000000002'],
            array_map(static fn(Enrolment $e): ?string => $e->siren()?->value(), $firstPage->enrolments()),
        );
        static::assertSame(2, $firstPage->nextOffset());
        static::assertSame(
            ['000000003'],
            array_map(static fn(Enrolment $e): ?string => $e->siren()?->value(), $lastPage->enrolments()),
        );
        static::assertTrue($lastPage->isLastPage());
        static::assertSame(2, $lastPage->offset());
        static::assertSame(2, $lastPage->limit());
        static::assertSame([], $past->enrolments());
    }

    public function testListingRefusesInvalidPaging(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();

        foreach ([[-1, 1, 'offset.negative'], [0, 0, 'limit.not_positive']] as [$offset, $limit, $code]) {
            try {
                $gateway->listEnrolments($tenant, $offset, $limit, null);
                static::fail('The paging must be refused.');
            } catch (InvalidValue $exception) {
                static::assertSame($code, $exception->violationCode());
            }
        }
        static::assertSame([], $gateway->listEnrolments($tenant, 0, 1, null)->enrolments());
    }

    public function testAPostingAnswersANewDossierWhoseCertificateIsInProgress(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();
        $request = SyntheticPostings::minimal();

        $first = $gateway->submitPosting($tenant, $request);
        $second = $gateway->submitPosting($tenant, $request);

        static::assertSame(self::FIRST_DOSSIER, $first->first()->value());
        static::assertSame(self::SECOND_DOSSIER, $second->first()->value());
        static::assertCount(1, $first->dossierIds());
        static::assertSame([$request, $request], $gateway->postings());
        $result = $gateway->getCertificate($tenant, $first->first());
        static::assertInstanceOf(CertificateInProgress::class, $result);
        static::assertSame(204, $result->httpStatus());
        static::assertTrue($result->errors()->isEmpty());
    }

    public function testTheTestDrivesTheCertificate(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $tenant = EnrolmentSamples::tenant();
        $dossier = $gateway->submitPosting($tenant, SyntheticPostings::minimal())->first();

        $gateway->makeCertificateAvailable($dossier);
        $available = $gateway->getCertificate($tenant, $dossier);
        static::assertInstanceOf(CertificateAvailable::class, $available);
        static::assertSame(200, $available->httpStatus());
        static::assertSame('application/pdf', $available->file()->contentType());
        static::assertNull($available->file()->contentDisposition());
        static::assertSame(SimulatedMobilityGateway::DEFAULT_CERTIFICATE_CONTENT, $available->file()->stream()->read(1000));

        $gateway->makeCertificateAvailable($dossier, 'custom');
        $custom = $gateway->getCertificate($tenant, $dossier);
        static::assertInstanceOf(CertificateAvailable::class, $custom);
        static::assertInstanceOf(SimulatedBinaryStream::class, $custom->file()->stream());
        static::assertSame('custom', $custom->file()->stream()->read(1000));
        // A fresh stream at every read.
        $again = $gateway->getCertificate($tenant, $dossier);
        static::assertInstanceOf(CertificateAvailable::class, $again);
        static::assertSame('custom', $again->file()->stream()->read(1000));

        $gateway->makeCertificateRefused($dossier);
        $refused = $gateway->getCertificate($tenant, $dossier);
        static::assertInstanceOf(CertificateRefusedOrUnknown::class, $refused);
        static::assertSame(404, $refused->httpStatus());

        $gateway->makeCertificateInProgress($dossier);
        static::assertInstanceOf(CertificateInProgress::class, $gateway->getCertificate($tenant, $dossier));
    }

    public function testAnUnknownDossierIsRefusedOrUnknown(): void
    {
        $result = (SimulatedMobilityGateway::create())->getCertificate(
            EnrolmentSamples::tenant(),
            DossierId::fromString(EnrolmentSamples::DOSSIER_ID),
        );

        static::assertInstanceOf(CertificateRefusedOrUnknown::class, $result);
        static::assertSame(404, $result->httpStatus());
        static::assertTrue($result->errors()->isEmpty());
    }

    public function testEveryCallIsRecordedWithItsTenant(): void
    {
        $gateway = SimulatedMobilityGateway::create();
        $one = TenantId::fromString('tenant-1');
        $two = TenantId::fromString('tenant-2');
        $enrolment = $gateway->requestEnrolment($one, Siren::fromString('000000001'), null, EnrolmentCategory::Rg);
        $gateway->listEnrolments($two, 0, 20, null);
        $gateway->getEnrolment($one, $enrolment->id());
        $gateway->deleteEnrolment($two, $enrolment->id());
        $dossier = $gateway->submitPosting($one, SyntheticPostings::minimal())->first();
        $gateway->getCertificate($two, $dossier);

        $calls = $gateway->calls();

        static::assertSame(
            [
                Operation::RequestEnrolment,
                Operation::ListEnrolments,
                Operation::GetEnrolment,
                Operation::DeleteEnrolment,
                Operation::SubmitPosting,
                Operation::GetCertificate,
            ],
            array_map(static fn(array $call): Operation => $call['operation'], $calls),
        );
        static::assertSame(
            ['tenant-1', 'tenant-2', 'tenant-1', 'tenant-2', 'tenant-1', 'tenant-2'],
            array_map(static fn(array $call): string => $call['tenantId']->value(), $calls),
        );
    }

    public function testTheGivenClockDatesTheEnrolments(): void
    {
        $gateway = new SimulatedMobilityGateway(new FrozenClock(new DateTimeImmutable('2030-05-06T07:08:09Z')));

        $enrolment = $gateway->requestEnrolment(EnrolmentSamples::tenant(), Siren::fromString('000000001'), null, EnrolmentCategory::Rg);

        static::assertSame('2030-05-06T07:08:09Z', $enrolment->requestedAt()?->toIso8601());
    }
}

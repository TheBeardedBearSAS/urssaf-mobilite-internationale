<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;

/**
 * Live checks against the URSSAF sandbox. Opt-in, never part of the coverage or mutation gates, and nothing recorded
 * here is committed (see docs/testing.md and the README, section "Sandbox contract tests").
 *
 * Skipped without URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET. Each test that needs data of the
 * sandbox (an enrolable SIREN, a company with a validated enrolment, a dossier number) is skipped, with the variable to
 * set, while that variable is missing: the posting is never sent with invented identifiers.
 */
#[Group('contract')]
final class SandboxContractTest extends TestCase
{
    private const UNKNOWN_ID = '00000000-0000-4000-8000-000000000000';

    private const PAGE_SIZE = 50;

    private const MAX_PAGES = 20;

    private const CERTIFICATE_STATUSES = [200, 204, 400, 404];

    private SandboxGateway $sandbox;

    protected function setUp(): void
    {
        $sandbox = SandboxGateway::fromEnvironment();
        if (!$sandbox instanceof SandboxGateway) {
            static::markTestSkipped(
                'URSSAF sandbox contract tests are opt-in: set URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET (see `make test-contract`).',
            );
        }

        $this->sandbox = $sandbox;
    }

    public function testObtainsAnAccessToken(): void
    {
        $token = $this->sandbox->tokenProvider->accessToken($this->sandbox->credentials, Operation::ListEnrolments);

        static::assertNotSame('', $token);
    }

    public function testListsTheFirstPageOfEnrolments(): void
    {
        $page = $this->sandbox->gateway->listEnrolments($this->sandbox->tenantId, 0, self::PAGE_SIZE, null);

        static::assertSame(0, $page->offset());
        static::assertSame(self::PAGE_SIZE, $page->limit());
        static::assertLessThanOrEqual(self::PAGE_SIZE, \count($page->enrolments()), 'The sandbox returned more enrolments than the limit asked.');
    }

    public function testAnUnknownEnrolmentIsNotFound(): void
    {
        try {
            $this->sandbox->gateway->getEnrolment($this->sandbox->tenantId, EnrolmentId::fromString(self::UNKNOWN_ID));
        } catch (UrssafResourceNotFound $notFound) {
            static::assertSame(Operation::GetEnrolment, $notFound->operation());
            static::assertSame(404, $notFound->httpStatus());

            return;
        } catch (UrssafRequestRejected $rejected) {
            // Documented by the Swagger only: 400 "Erreur Fonctionnelle" on GET /enrolements/{id}.
            static::assertSame(Operation::GetEnrolment, $rejected->operation());
            static::assertSame(400, $rejected->httpStatus());

            return;
        }

        static::fail('An unknown enrolment must be answered by 404 (or 400).');
    }

    public function testEnrolmentLifeCycle(): void
    {
        $siren = SandboxData::enrolmentSiren();
        if (!$siren instanceof Siren) {
            static::markTestSkipped('Set URSSAF_SANDBOX_ENROLMENT_SIREN (and optionally _NIC, _CATEGORY) to run the enrolment life-cycle.');
        }

        $nic = SandboxData::enrolmentNic();
        $gateway = $this->sandbox->gateway;
        $tenant = $this->sandbox->tenantId;

        try {
            $requested = $gateway->requestEnrolment($tenant, $siren, $nic, SandboxData::enrolmentCategory());
        } catch (UrssafRequestRejected $rejected) {
            // Documented business rejection (enrolment already existing, unknown company): the envelope must be readable.
            static::assertSame(Operation::RequestEnrolment, $rejected->operation());
            static::assertSame(400, $rejected->httpStatus());
            static::markTestSkipped(\sprintf('The sandbox rejected the enrolment request (%d error(s)): use another SIREN or delete the existing enrolment.', $rejected->errors()->count()));
        }

        try {
            $echoed = $requested->siren();
            static::assertInstanceOf(Siren::class, $echoed, 'The created enrolment echoes its SIREN.');
            static::assertTrue($siren->equals($echoed));

            $listed = $this->findInList($requested->id());
            static::assertInstanceOf(Enrolment::class, $listed, 'The new enrolment must appear in GET /enrolements.');

            $fetched = $gateway->getEnrolment($tenant, $requested->id());
            static::assertTrue($requested->id()->equals($fetched->id()));
            static::assertContains(
                $fetched->status(),
                [EnrolmentStatus::Encours, EnrolmentStatus::Accepte, EnrolmentStatus::Refuse],
                'Unexpected status of a new enrolment: ' . ($fetched->rawStatus() ?? 'none'),
            );
        } finally {
            $gateway->deleteEnrolment($tenant, $requested->id());
        }

        try {
            $afterDeletion = $gateway->getEnrolment($tenant, $requested->id());
        } catch (UrssafResourceNotFound) {
            // Not found after the deletion: one of the two documented outcomes.
            return;
        }

        static::assertSame(EnrolmentStatus::Supprime, $afterDeletion->status(), 'A deleted enrolment is either not found or SUPPRIME.');
    }

    public function testSubmitsAPostingThenReadsItsCertificateStatus(): void
    {
        $posting = SandboxData::posting();
        if (!$posting instanceof PostingRequest) {
            static::markTestSkipped('Set URSSAF_SANDBOX_POSTING_SIREN and URSSAF_SANDBOX_POSTING_NIC (company with a validated enrolment) to submit a posting: nothing is sent otherwise.');
        }

        $receipt = $this->sandbox->gateway->submitPosting($this->sandbox->tenantId, $posting);

        static::assertNotSame([], $receipt->dossierIds());

        $result = $this->sandbox->gateway->getCertificate($this->sandbox->tenantId, $receipt->first());

        static::assertCertificateResultIsConsistent($result);
    }

    public function testCertificateOfAnUnknownDossierIsNotDownloadable(): void
    {
        $result = $this->sandbox->gateway->getCertificate($this->sandbox->tenantId, DossierId::fromString(self::UNKNOWN_ID));

        static::assertNotInstanceOf(CertificateAvailable::class, $result);
        static::assertCertificateResultIsConsistent($result);
    }

    public function testCertificateOfAGivenDossier(): void
    {
        $dossierId = SandboxData::dossierId();
        if ($dossierId === null) {
            static::markTestSkipped('Set URSSAF_SANDBOX_DOSSIER_ID (a dossier number of your sandbox) to observe the certificate status.');
        }

        $result = $this->sandbox->gateway->getCertificate($this->sandbox->tenantId, DossierId::fromString($dossierId));

        static::assertCertificateResultIsConsistent($result);
    }

    private function findInList(EnrolmentId $id): ?Enrolment
    {
        $offset = 0;
        for ($pageNumber = 0; $pageNumber < self::MAX_PAGES; ++$pageNumber) {
            $page = $this->sandbox->gateway->listEnrolments($this->sandbox->tenantId, $offset, self::PAGE_SIZE, null);
            foreach ($page->enrolments() as $enrolment) {
                if ($enrolment->id()->equals($id)) {
                    return $enrolment;
                }
            }

            $next = $page->nextOffset();
            if ($next === null) {
                return null;
            }

            $offset = $next;
        }

        return null;
    }

    private static function assertCertificateResultIsConsistent(CertificateResult $result): void
    {
        static::assertContains($result->httpStatus(), self::CERTIFICATE_STATUSES, 'Unexpected certificate status.');

        $expected = match ($result->httpStatus()) {
            200 => CertificateAvailable::class,
            404 => CertificateRefusedOrUnknown::class,
            default => CertificateInProgress::class,
        };

        static::assertInstanceOf($expected, $result);
    }
}

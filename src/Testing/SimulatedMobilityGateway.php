<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Testing;

use DateTimeImmutable;
use LogicException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;

/**
 * An in-memory URSSAF "Mobilité internationale" platform for the tests of a host application: it answers the six
 * operations like the real API would, without any network. Never use it outside tests: nothing is sent to URSSAF.
 *
 * Default behaviour (the outcome of an enrolment and of a certificate is driven by the test):
 * - a new enrolment is ENCOURS; {@see self::acceptEnrolment()} / {@see self::refuseEnrolment()} settle it; asking for
 *   the enrolment of a SIREN that already has a not deleted one is a 400 ({@see UrssafRequestRejected});
 * - the enrolments are global (URSSAF identifies the issuer by its credentials only, ADR 0007), listed in creation
 *   order; "sort" is ignored; a deleted enrolment stays readable and listed with the status SUPPRIME;
 * - an unknown enrolment is a 404 ({@see UrssafResourceNotFound});
 * - a posting is always accepted and answers one new dossier id; the enrolment of the company is not checked (use the
 *   guard of {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPosting::afterCheckingEnrolment()});
 * - the certificate of a new dossier is in progress; {@see self::makeCertificateAvailable()},
 *   {@see self::makeCertificateInProgress()} and {@see self::makeCertificateRefused()} drive it; an unknown dossier is
 *   "refused or unknown" (404);
 * - identifiers are deterministic: enrolments 00000000-0000-4000-8000-000000000001..., dossiers
 *   00000000-0000-4000-9000-000000000001...
 *
 * {@see self::forEnvironment()} refuses the production environment.
 *
 * @mago-expect lint:too-many-methods One method per operation of the port plus the scripting and inspection API of the simulator.
 */
final class SimulatedMobilityGateway implements MobilityGateway
{
    public const DEFAULT_CERTIFICATE_CONTENT = '%PDF-1.4 simulated certificate';

    private const NOW = '2026-01-01T00:00:00Z';

    /** @var array<string, Enrolment> */
    private array $enrolments = [];

    /** @var array<string, Siren> */
    private array $sirens = [];

    /** @var array<string, SimulatedCertificate> */
    private array $certificates = [];

    /** @var list<array{operation: Operation, tenantId: TenantId}> */
    private array $calls = [];

    /** @var list<PostingRequest> */
    private array $postings = [];

    private int $enrolmentSequence = 0;

    private int $dossierSequence = 0;

    /**
     * @param Clock $clock dates the enrolments (creation and settlement)
     */
    public function __construct(private readonly Clock $clock) {}

    /**
     * A simulator whose clock is frozen on 2026-01-01T00:00:00Z.
     */
    public static function create(): self
    {
        return new self(new FrozenClock(new DateTimeImmutable(self::NOW)));
    }

    /**
     * Same as {@see self::create()}, for a host that knows its environment and wants the guard to check it.
     *
     * @throws LogicException for the production environment
     */
    public static function forEnvironment(Environment $environment): self
    {
        if ($environment === Environment::Production) {
            throw new LogicException('The simulated gateway is meant for tests and cannot be used with the production environment.');
        }

        return self::create();
    }

    /**
     * Settles an enrolment as ACCEPTE.
     *
     * @throws LogicException for an unknown enrolment
     */
    public function acceptEnrolment(EnrolmentId $enrolmentId): void
    {
        $this->settle($enrolmentId, EnrolmentStatus::Accepte);
    }

    /**
     * Settles an enrolment as REFUSE.
     *
     * @throws LogicException for an unknown enrolment
     */
    public function refuseEnrolment(EnrolmentId $enrolmentId): void
    {
        $this->settle($enrolmentId, EnrolmentStatus::Refuse);
    }

    public function makeCertificateAvailable(DossierId $dossierId, string $content = self::DEFAULT_CERTIFICATE_CONTENT): void
    {
        $this->certificates[$dossierId->value()] = SimulatedCertificate::available($content);
    }

    public function makeCertificateInProgress(DossierId $dossierId): void
    {
        $this->certificates[$dossierId->value()] = SimulatedCertificate::inProgress();
    }

    public function makeCertificateRefused(DossierId $dossierId): void
    {
        $this->certificates[$dossierId->value()] = SimulatedCertificate::refused();
    }

    /**
     * Every operation called, in order, with the tenant.
     *
     * @return list<array{operation: Operation, tenantId: TenantId}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Every posting request accepted so far, in order (personal data: for the assertions of the tests only).
     *
     * @return list<PostingRequest>
     */
    public function postings(): array
    {
        return $this->postings;
    }

    public function requestEnrolment(TenantId $tenantId, Siren $siren, ?Nic $nic, EnrolmentCategory $category): Enrolment
    {
        $this->record(Operation::RequestEnrolment, $tenantId);
        foreach ($this->enrolments as $key => $existing) {
            if ($this->sirens[$key]->equals($siren) && $existing->status() !== EnrolmentStatus::Supprime) {
                throw UrssafRequestRejected::withErrors(Operation::RequestEnrolment, 400, ApiErrors::none());
            }
        }
        $now = Instant::fromDateTime($this->clock->now());
        $id = EnrolmentId::fromString(\sprintf('00000000-0000-4000-8000-%012d', ++$this->enrolmentSequence));
        $enrolment = Enrolment::of($id, $siren, $nic, $category->value, EnrolmentStatus::Encours->value, $now, $now);
        $this->enrolments[$id->value()] = $enrolment;
        $this->sirens[$id->value()] = $siren;

        return $enrolment;
    }

    public function listEnrolments(TenantId $tenantId, int $offset, int $limit, ?string $sort): EnrolmentPage
    {
        $this->record(Operation::ListEnrolments, $tenantId);

        // EnrolmentPage refuses a negative offset and a limit below 1, as the HTTP gateway does.
        return EnrolmentPage::of(\array_slice(array_values($this->enrolments), $offset, $limit), $offset, $limit);
    }

    public function getEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): Enrolment
    {
        $this->record(Operation::GetEnrolment, $tenantId);

        return $this->enrolments[$enrolmentId->value()]
            ?? throw UrssafResourceNotFound::withErrors(Operation::GetEnrolment, ApiErrors::none());
    }

    public function deleteEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): void
    {
        $this->record(Operation::DeleteEnrolment, $tenantId);
        if (!\array_key_exists($enrolmentId->value(), $this->enrolments)) {
            throw UrssafResourceNotFound::withErrors(Operation::DeleteEnrolment, ApiErrors::none());
        }
        $this->settle($enrolmentId, EnrolmentStatus::Supprime);
    }

    public function submitPosting(TenantId $tenantId, PostingRequest $request): PostingReceipt
    {
        $this->record(Operation::SubmitPosting, $tenantId);
        $this->postings[] = $request;
        $id = DossierId::fromString(\sprintf('00000000-0000-4000-9000-%012d', ++$this->dossierSequence));
        $this->certificates[$id->value()] = SimulatedCertificate::inProgress();

        return PostingReceipt::of($id);
    }

    public function getCertificate(TenantId $tenantId, DossierId $dossierId): CertificateResult
    {
        $this->record(Operation::GetCertificate, $tenantId);
        $certificate = $this->certificates[$dossierId->value()] ?? null;

        return $certificate?->toResult() ?? CertificateRefusedOrUnknown::of(404, ApiErrors::none());
    }

    private function record(Operation $operation, TenantId $tenantId): void
    {
        $this->calls[] = ['operation' => $operation, 'tenantId' => $tenantId];
    }

    private function settle(EnrolmentId $enrolmentId, EnrolmentStatus $status): void
    {
        $current = $this->enrolments[$enrolmentId->value()] ?? throw new LogicException('Unknown simulated enrolment.');
        $this->enrolments[$enrolmentId->value()] = Enrolment::of(
            $current->id(),
            $current->siren(),
            $current->nic(),
            $current->rawCategory(),
            $status->value,
            $current->requestedAt(),
            Instant::fromDateTime($this->clock->now()),
        );
    }
}

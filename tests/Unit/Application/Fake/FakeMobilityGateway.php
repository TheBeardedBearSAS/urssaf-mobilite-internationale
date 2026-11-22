<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake;

use LogicException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use Throwable;

/**
 * Scripted gateway: each operation answers with the next scripted response of its own queue (a value, or a throwable to
 * throw), and records its call. An operation called without a scripted response fails the test.
 *
 * @mago-expect lint:too-many-methods One scripting method and one gateway method per operation of the port.
 */
final class FakeMobilityGateway implements MobilityGateway
{
    /** @var array<string, list<object|null>> */
    private array $responses = [];

    /** @var list<array{operation: Operation, tenantId: TenantId, arguments: list<mixed>}> */
    private array $calls = [];

    public function willRequestEnrolment(Enrolment|Throwable $response): void
    {
        $this->responses[Operation::RequestEnrolment->value][] = $response;
    }

    public function willListEnrolments(EnrolmentPage|Throwable $response): void
    {
        $this->responses[Operation::ListEnrolments->value][] = $response;
    }

    public function willGetEnrolment(Enrolment|Throwable $response): void
    {
        $this->responses[Operation::GetEnrolment->value][] = $response;
    }

    public function willDeleteEnrolment(): void
    {
        $this->responses[Operation::DeleteEnrolment->value][] = null;
    }

    public function willFailToDeleteEnrolment(Throwable $failure): void
    {
        $this->responses[Operation::DeleteEnrolment->value][] = $failure;
    }

    public function willSubmitPosting(PostingReceipt|Throwable $response): void
    {
        $this->responses[Operation::SubmitPosting->value][] = $response;
    }

    public function willGetCertificate(CertificateResult|Throwable $response): void
    {
        $this->responses[Operation::GetCertificate->value][] = $response;
    }

    /**
     * @return list<array{operation: Operation, tenantId: TenantId, arguments: list<mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<Operation>
     */
    public function operations(): array
    {
        return array_map(static fn(array $call): Operation => $call['operation'], $this->calls);
    }

    public function requestEnrolment(TenantId $tenantId, Siren $siren, ?Nic $nic, EnrolmentCategory $category): Enrolment
    {
        $response = $this->answer(Operation::RequestEnrolment, $tenantId, [$siren, $nic, $category]);
        \assert($response instanceof Enrolment, "The scripted response matches the operation.");

        return $response;
    }

    public function listEnrolments(TenantId $tenantId, int $offset, int $limit, ?string $sort): EnrolmentPage
    {
        $response = $this->answer(Operation::ListEnrolments, $tenantId, [$offset, $limit, $sort]);
        \assert($response instanceof EnrolmentPage, "The scripted response matches the operation.");

        return $response;
    }

    public function getEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): Enrolment
    {
        $response = $this->answer(Operation::GetEnrolment, $tenantId, [$enrolmentId]);
        \assert($response instanceof Enrolment, "The scripted response matches the operation.");

        return $response;
    }

    public function deleteEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): void
    {
        $this->answer(Operation::DeleteEnrolment, $tenantId, [$enrolmentId]);
    }

    public function submitPosting(TenantId $tenantId, PostingRequest $request): PostingReceipt
    {
        $response = $this->answer(Operation::SubmitPosting, $tenantId, [$request]);
        \assert($response instanceof PostingReceipt, "The scripted response matches the operation.");

        return $response;
    }

    public function getCertificate(TenantId $tenantId, DossierId $dossierId): CertificateResult
    {
        $response = $this->answer(Operation::GetCertificate, $tenantId, [$dossierId]);
        \assert($response instanceof CertificateResult, "The scripted response matches the operation.");

        return $response;
    }

    /**
     * @param list<mixed> $arguments
     */
    private function answer(Operation $operation, TenantId $tenantId, array $arguments): ?object
    {
        $this->calls[] = ['operation' => $operation, 'tenantId' => $tenantId, 'arguments' => $arguments];
        $queue = $this->responses[$operation->value] ?? [];
        if ($queue === []) {
            throw new LogicException(\sprintf('Unexpected %s call.', $operation->value));
        }
        $response = array_shift($queue);
        $this->responses[$operation->value] = $queue;

        return $response instanceof Throwable ? throw $response : $response;
    }
}

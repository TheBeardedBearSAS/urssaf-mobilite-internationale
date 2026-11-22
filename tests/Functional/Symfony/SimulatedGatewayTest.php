<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;

/**
 * "gateway.simulated: true": the handlers run against the in-memory URSSAF API, as in the tests of a host application.
 */
final class SimulatedGatewayTest extends TestCase
{
    public function testTheRealGatewayIsUsedByDefault(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UrssafHttpGateway::class, $harness->privateService(MobilityGateway::class, MobilityGateway::class));
        static::assertFalse($harness->has(SimulatedMobilityGateway::class));
    }

    public function testTheHandlersAnswerFromTheSimulatorWithoutNetwork(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $simulator = $harness->get(SimulatedMobilityGateway::class);
        static::assertSame($simulator, $harness->privateService(MobilityGateway::class, MobilityGateway::class));
        $tenant = EnrolmentSamples::tenant();

        $enrolment = $harness->get(RequestEnrolmentHandler::class)(new RequestEnrolment($tenant, Siren::fromString('000000000'), null, EnrolmentCategory::Rg));
        $simulator->makeCertificateInProgress(EnrolmentSamples::dossierId());
        $certificate = $harness->get(GetCertificateHandler::class)(new GetCertificate($tenant, EnrolmentSamples::dossierId()));

        static::assertSame(EnrolmentStatus::Encours, $enrolment->status());
        static::assertInstanceOf(CertificateInProgress::class, $certificate);
        static::assertSame(
            [Operation::RequestEnrolment, Operation::GetCertificate],
            array_map(static fn(array $call): Operation => $call['operation'], $simulator->calls()),
        );
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertSame([], $harness->client->getRequests());
    }
}

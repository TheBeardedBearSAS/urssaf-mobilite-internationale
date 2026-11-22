<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\FakeMobilityGateway;

#[CoversClass(RequestEnrolment::class)]
#[CoversClass(RequestEnrolmentHandler::class)]
#[CoversClass(DeleteEnrolment::class)]
#[CoversClass(DeleteEnrolmentHandler::class)]
final class EnrolmentHandlersTest extends TestCase
{
    public function testRequestEnrolmentCallsTheGatewayOnceAndReturnsItsEnrolment(): void
    {
        $enrolment = EnrolmentSamples::enrolment('ENCOURS');
        $gateway = new FakeMobilityGateway();
        $gateway->willRequestEnrolment($enrolment);
        $siren = Siren::fromString('000000000');
        $nic = Nic::fromString('00001');
        $command = new RequestEnrolment(EnrolmentSamples::tenant(), $siren, $nic, EnrolmentCategory::Rg);

        $result = (new RequestEnrolmentHandler($gateway))($command);

        static::assertSame($enrolment, $result);
        static::assertCount(1, $gateway->calls());
        static::assertSame(Operation::RequestEnrolment, $gateway->calls()[0]['operation']);
        static::assertSame(EnrolmentSamples::tenant()->value(), $gateway->calls()[0]['tenantId']->value());
        static::assertSame([$siren, $nic, EnrolmentCategory::Rg], $gateway->calls()[0]['arguments']);
        static::assertSame($siren, $command->siren);
        static::assertSame($nic, $command->nic);
        static::assertSame(EnrolmentCategory::Rg, $command->category);
    }

    public function testRequestEnrolmentFailureIsNotRetried(): void
    {
        $failure = new RuntimeException('boom');
        $gateway = new FakeMobilityGateway();
        $gateway->willRequestEnrolment($failure);
        $handler = new RequestEnrolmentHandler($gateway);
        $command = new RequestEnrolment(EnrolmentSamples::tenant(), Siren::fromString('000000000'), null, EnrolmentCategory::Autre);

        try {
            $handler($command);
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertCount(1, $gateway->calls());
        static::assertNull($command->nic);
    }

    public function testDeleteEnrolmentCallsTheGatewayOnce(): void
    {
        $gateway = new FakeMobilityGateway();
        $gateway->willDeleteEnrolment();
        $command = new DeleteEnrolment(EnrolmentSamples::tenant(), EnrolmentSamples::enrolmentId());

        (new DeleteEnrolmentHandler($gateway))($command);

        static::assertSame([Operation::DeleteEnrolment], $gateway->operations());
        static::assertSame(EnrolmentSamples::tenant()->value(), $gateway->calls()[0]['tenantId']->value());
        static::assertSame([$command->enrolmentId], $gateway->calls()[0]['arguments']);
        static::assertSame(EnrolmentSamples::ENROLMENT_ID, $command->enrolmentId->value());
    }

    public function testDeleteEnrolmentFailureIsNotRetried(): void
    {
        $failure = new RuntimeException('boom');
        $gateway = new FakeMobilityGateway();
        $gateway->willFailToDeleteEnrolment($failure);

        try {
            (new DeleteEnrolmentHandler($gateway))(new DeleteEnrolment(EnrolmentSamples::tenant(), EnrolmentSamples::enrolmentId()));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertCount(1, $gateway->calls());
    }
}

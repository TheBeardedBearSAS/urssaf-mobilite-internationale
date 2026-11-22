<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolments;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\FakeMobilityGateway;

#[CoversClass(ListEnrolments::class)]
#[CoversClass(ListEnrolmentsHandler::class)]
#[CoversClass(GetEnrolment::class)]
#[CoversClass(GetEnrolmentHandler::class)]
#[CoversClass(GetCertificate::class)]
#[CoversClass(GetCertificateHandler::class)]
final class QueryHandlersTest extends TestCase
{
    public function testListEnrolmentsDefaultsToTheFirstPageOfTwentyWithoutSort(): void
    {
        $query = ListEnrolments::firstPage(EnrolmentSamples::tenant());

        static::assertSame(0, $query->offset);
        static::assertSame(20, $query->limit);
        static::assertNull($query->sort);
        static::assertSame(EnrolmentSamples::tenant()->value(), $query->tenantId->value());
    }

    public function testListEnrolmentsPassesItsArgumentsAndReturnsThePage(): void
    {
        $page = EnrolmentPage::of([EnrolmentSamples::enrolment('ACCEPTE')], 40, 10);
        $gateway = new FakeMobilityGateway();
        $gateway->willListEnrolments($page);

        $result = (new ListEnrolmentsHandler($gateway))(new ListEnrolments(EnrolmentSamples::tenant(), 40, 10, 'dateDemande'));

        static::assertSame($page, $result);
        static::assertSame([Operation::ListEnrolments], $gateway->operations());
        static::assertSame([40, 10, 'dateDemande'], $gateway->calls()[0]['arguments']);
    }

    public function testListEnrolmentsAcceptsTheLowestValues(): void
    {
        $query = new ListEnrolments(EnrolmentSamples::tenant(), 0, 1, 'a');

        static::assertSame(0, $query->offset);
        static::assertSame(1, $query->limit);
        static::assertSame('a', $query->sort);
    }

    /**
     * @return iterable<string, array{int, int, ?string, string}>
     */
    public static function invalidListings(): iterable
    {
        yield 'negative offset' => [-1, 20, null, 'offset.negative'];
        yield 'zero limit' => [0, 0, null, 'limit.not_positive'];
        yield 'negative limit' => [0, -5, null, 'limit.not_positive'];
        yield 'empty sort' => [0, 20, '', 'sort.empty'];
        yield 'blank sort' => [0, 20, '  ', 'sort.empty'];
    }

    #[DataProvider('invalidListings')]
    public function testListEnrolmentsRefusesInvalidArguments(int $offset, int $limit, ?string $sort, string $code): void
    {
        try {
            new ListEnrolments(EnrolmentSamples::tenant(), $offset, $limit, $sort);
            static::fail('The arguments must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testGetEnrolmentReturnsTheGatewayEnrolment(): void
    {
        $enrolment = EnrolmentSamples::enrolment('ENCOURS');
        $gateway = new FakeMobilityGateway();
        $gateway->willGetEnrolment($enrolment);
        $query = new GetEnrolment(EnrolmentSamples::tenant(), EnrolmentSamples::enrolmentId());

        static::assertSame($enrolment, (new GetEnrolmentHandler($gateway))($query));
        static::assertSame([Operation::GetEnrolment], $gateway->operations());
        static::assertSame([$query->enrolmentId], $gateway->calls()[0]['arguments']);
        static::assertSame(EnrolmentSamples::tenant()->value(), $query->tenantId->value());
    }

    public function testGetCertificateReturnsTheGatewayResult(): void
    {
        $result = CertificateInProgress::of(204, ApiErrors::none());
        $gateway = new FakeMobilityGateway();
        $gateway->willGetCertificate($result);
        $query = new GetCertificate(EnrolmentSamples::tenant(), EnrolmentSamples::dossierId());

        static::assertSame($result, (new GetCertificateHandler($gateway))($query));
        static::assertSame([Operation::GetCertificate], $gateway->operations());
        static::assertSame([$query->dossierId], $gateway->calls()[0]['arguments']);
        static::assertSame(EnrolmentSamples::tenant()->value(), $query->tenantId->value());
    }

    public function testFailuresPropagateWithoutRetry(): void
    {
        $failure = new RuntimeException('boom');
        $gateway = new FakeMobilityGateway();
        $gateway->willListEnrolments($failure);
        $gateway->willGetEnrolment($failure);
        $gateway->willGetCertificate($failure);
        $tenant = EnrolmentSamples::tenant();
        try {
            (new ListEnrolmentsHandler($gateway))(ListEnrolments::firstPage($tenant));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        try {
            (new GetEnrolmentHandler($gateway))(new GetEnrolment($tenant, EnrolmentSamples::enrolmentId()));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        try {
            (new GetCertificateHandler($gateway))(new GetCertificate($tenant, EnrolmentSamples::dossierId()));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertCount(3, $gateway->calls());
    }
}

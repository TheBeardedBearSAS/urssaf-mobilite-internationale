<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\CredentialsNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayEnrolmentTest extends TestCase
{
    private const ID = Calls::ENROLMENT_ID;

    public function testRequestEnrolmentPostsTheBodyAndReadsTheCreatedEnrolment(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(201, Calls::ENROLMENT);

        $enrolment = $stack->gateway()->requestEnrolment(
            Stacks::tenant(),
            Siren::fromString('000000000'),
            Nic::fromString('00001'),
            EnrolmentCategory::Rg,
        );

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/enrolements', $request->getUri()->getPath());
        static::assertSame('{"siren":"000000000","nic":"00001","categorie":"RG"}', (string) $request->getBody());
        static::assertSame(self::ID, $enrolment->id()->value());
        static::assertSame(EnrolmentStatus::Encours, $enrolment->status());
        static::assertSame(EnrolmentCategory::Rg, $enrolment->category());
    }

    public function testRequestEnrolmentOmitsAnAbsentNic(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(201, '{"idEnrolement":"' . self::ID . '"}');

        $stack->gateway()->requestEnrolment(Stacks::tenant(), Siren::fromString('000000000'), null, EnrolmentCategory::Autre);

        static::assertSame('{"siren":"000000000","categorie":"AUTRE"}', (string) $stack->lastRequest()->getBody());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unknownEnrolmentOutcomes(): iterable
    {
        yield 'a 200 instead of the 201' => [200, '{"idEnrolement":"' . self::ID . '"}'];
        yield 'a 202' => [202, '{"idEnrolement":"' . self::ID . '"}'];
        yield 'invalid json' => [201, '<html>'];
        yield 'empty body' => [201, ''];
        yield 'missing identifier' => [201, '{"siren":"000000000"}'];
        yield 'invalid identifier' => [201, '{"idEnrolement":"not-a-uuid"}'];
        yield 'a list' => [201, '[{"idEnrolement":"' . self::ID . '"}]'];
        yield 'malformed siren' => [201, '{"idEnrolement":"' . self::ID . '","siren":"12"}'];
        yield 'malformed date' => [201, '{"idEnrolement":"' . self::ID . '","dateDemande":"yesterday"}'];
        yield 'server error' => [500, ''];
        yield 'bad gateway' => [502, '<html>'];
    }

    #[DataProvider('unknownEnrolmentOutcomes')]
    public function testRequestEnrolmentAnswersWithAnUnknownOutcomeWhenItMayHaveBeenRecorded(int $status, string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body);

        try {
            $stack->gateway()->requestEnrolment(Stacks::tenant(), Siren::fromString('000000000'), null, EnrolmentCategory::Rg);
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(Operation::RequestEnrolment, $exception->operation());
            static::assertSame($status, $exception->httpStatus());
        }
        static::assertCount(1, $stack->apiRequests());
    }

    public function testRequestEnrolmentAnswersWithAnUnknownOutcomeAfterATransportError(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('reset'));

        try {
            $stack->gateway()->requestEnrolment(Stacks::tenant(), Siren::fromString('000000000'), null, EnrolmentCategory::Rg);
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(TransportFailure::class, $exception->causeClass());
        }
    }

    public function testRequestEnrolmentRejectionsAreNotUnknownOutcomes(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '{"code":"exists","message":"already enrolled"}');

        try {
            $stack->gateway()->requestEnrolment(Stacks::tenant(), Siren::fromString('000000000'), null, EnrolmentCategory::Rg);
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->hasErrorCode('exists'));
        }
    }

    public function testTheCredentialsOfTheTenantAreUsedAndAnUnknownTenantFailsBeforeAnyCall(): void
    {
        $stack = Stacks::sandbox();
        $gateway = UrssafGatewayFactory::create(
            new HttpTransport($stack->http, new Psr17Factory(), new Psr17Factory()),
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-of-tenant-1', 'secret-of-tenant-1')]),
            $stack->tokens,
            $stack->configuration,
        );
        $stack->respondToken();
        $stack->respond(200, Calls::ENROLMENT);

        $gateway->getEnrolment(Stacks::tenant(), EnrolmentId::fromString(self::ID));

        $form = [];
        parse_str((string) $stack->requests()[0]->getBody(), $form);
        static::assertSame('client-of-tenant-1', $form['client_id'] ?? null);
        static::assertCount(2, $stack->requests());

        $unknown = TenantId::fromString('tenant-unknown');
        try {
            $gateway->deleteEnrolment($unknown, EnrolmentId::fromString(self::ID));
            static::fail('The tenant has no credentials.');
        } catch (CredentialsNotFound $exception) {
            static::assertSame('credentials.not_found', $exception->violationCode());
        }
        static::assertCount(2, $stack->requests(), 'Nothing was sent for the unknown tenant.');
    }

    public function testTheOptionsAreAppliedToTheGateway(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        $stack->gatewayWith(GatewayOptions::defaults()->withCertificateBadRequestAsInProgress(false))
            ->listEnrolments(Stacks::tenant(), 0, 1, null);

        static::assertSame(['tenant-1:get-enrolement-list'], $stack->rateLimiter->acquired());
    }
}

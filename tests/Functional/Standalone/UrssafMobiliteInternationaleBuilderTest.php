<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Standalone;

use DateTimeImmutable;
use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPosting;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolments;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Standalone\UrssafMobiliteInternationale;
use TheBeardedBear\UrssafMobiliteInternationale\Standalone\UrssafMobiliteInternationaleBuilder;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;

/**
 * @mago-expect lint:too-many-methods One test per builder option and per guard order; splitting them would only scatter the shared wiring.
 */
#[CoversClass(UrssafMobiliteInternationaleBuilder::class)]
#[CoversClass(UrssafMobiliteInternationale::class)]
final class UrssafMobiliteInternationaleBuilderTest extends TestCase
{
    private const ISSUED = 'fictitious-access-token';

    public function testTheEnrolmentUseCasesRunEndToEndOverHttp(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(201, Calls::ENROLMENT));
        $http->addResponse(self::json(200, '[' . Calls::ENROLMENT . ']'));
        $http->addResponse(self::json(200, Calls::ENROLMENT));
        $http->addResponse(self::json(200, ''));
        $package = self::builder($http)->build();
        $tenant = EnrolmentSamples::tenant();
        $id = EnrolmentId::fromString(Calls::ENROLMENT_ID);

        $created = $package->requestEnrolment()(new RequestEnrolment($tenant, Siren::fromString('000000000'), null, EnrolmentCategory::Rg));
        $page = $package->listEnrolments()(ListEnrolments::firstPage($tenant));
        $read = $package->getEnrolment()(new GetEnrolment($tenant, $id));
        $package->deleteEnrolment()(new DeleteEnrolment($tenant, $id));

        static::assertSame(Calls::ENROLMENT_ID, $created->id()->value());
        static::assertCount(1, $page->enrolments());
        static::assertSame(EnrolmentStatus::Encours, $read->status());
        $requests = $http->getRequests();
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $requests[0]->getUri());
        static::assertSame(
            ['POST /tiersdecl/v1/mobilite/enrolements', 'GET /tiersdecl/v1/mobilite/enrolements', 'GET /tiersdecl/v1/mobilite/enrolements/' . Calls::ENROLMENT_ID, 'DELETE /tiersdecl/v1/mobilite/enrolements/' . Calls::ENROLMENT_ID],
            array_map(static fn(RequestInterface $request): string => $request->getMethod() . ' ' . $request->getUri()->getPath(), \array_slice($requests, 1)),
        );
        static::assertSame('offset=0&limit=20', $requests[2]->getUri()->getQuery());
        static::assertSame('Bearer ' . self::ISSUED, $requests[1]->getHeaderLine('Authorization'));
    }

    public function testThePostingAndCertificateUseCasesRunEndToEndOverHttp(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[{"id":"' . Calls::DOSSIER_ID . '"}]'));
        $http->addResponse(new Response(204));
        $package = self::builder($http)->build();
        $tenant = EnrolmentSamples::tenant();

        $receipt = $package->submitPosting()(SubmitPosting::of($tenant, SyntheticPostings::minimal()));
        $certificate = $package->getCertificate()(new GetCertificate($tenant, $receipt->first()));

        static::assertSame(Calls::DOSSIER_ID, $receipt->first()->value());
        static::assertInstanceOf(CertificateInProgress::class, $certificate);
        $paths = array_map(static fn(RequestInterface $request): string => $request->getMethod() . ' ' . $request->getUri()->getPath(), $http->getRequests());
        static::assertSame(
            ['POST /api/oauth/v1/token', 'POST /tiersdecl/v1/mobilite/declaration/detachement/UE', 'GET /tiersdecl/v1/mobilite/certificat/' . Calls::DOSSIER_ID],
            $paths,
        );
    }

    public function testEveryWithMethodReturnsANewBuilderAndLeavesTheOriginalUntouched(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));
        $base = self::builder($http);
        $derived = [
            $base->withEnvironment(Environment::Production),
            $base->withBaseUri('https://example.test/api'),
            $base->withTokenUri('https://example.test/token'),
            $base->withTokenStore(new InMemoryTokenStore()),
            $base->withOptions(GatewayOptions::defaults()),
            $base->withLogger(new CapturingLogger()),
            $base->withClock(new FrozenClock(new DateTimeImmutable('2030-01-01T00:00:00Z'))),
            $base->withValidationPolicy(PostingValidationPolicy::default()->withPdfTeleworkRules()),
            $base->withGateway(SimulatedMobilityGateway::create()),
        ];

        foreach ($derived as $builder) {
            static::assertNotSame($base, $builder);
        }
        $page = $base->build()->listEnrolments()(ListEnrolments::firstPage(EnrolmentSamples::tenant()));
        static::assertSame([], $page->enrolments());
        static::assertSame('api-edi.urssaf.fr', $http->getRequests()[1]->getUri()->getHost());
    }

    public function testTheEnvironmentAndTheUrisAreUsed(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));
        $tenant = EnrolmentSamples::tenant();

        self::builder($http)
            ->withEnvironment(Environment::Production)
            ->withBaseUri('https://gateway.example.test/mi/')
            ->withTokenUri('https://auth.example.test/oauth/token')
            ->build()
            ->listEnrolments()(ListEnrolments::firstPage($tenant));

        $requests = $http->getRequests();
        static::assertSame('https://auth.example.test/oauth/token', (string) $requests[0]->getUri());
        static::assertSame('https://gateway.example.test/mi/enrolements', explode('?', (string) $requests[1]->getUri())[0]);
    }

    public function testTheProductionEnvironmentTargetsTheProductionHost(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));

        self::builder($http)->withEnvironment(Environment::Production)->build()->listEnrolments()(ListEnrolments::firstPage(EnrolmentSamples::tenant()));

        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', (string) $http->getRequests()[0]->getUri());
    }

    public function testAnInsecureUriIsRefusedWhenBuilding(): void
    {
        $this->expectException(InvalidValue::class);

        self::builder(new Client())->withBaseUri('http://insecure.example.test')->build();
    }

    public function testAnInsecureTokenUriIsRefusedWhenBuilding(): void
    {
        $this->expectException(InvalidValue::class);

        self::builder(new Client())->withTokenUri('http://insecure.example.test/token')->build();
    }

    public function testTheGivenTokenStoreIsUsedAndTheTokenIsReusedAcrossCalls(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));
        $http->addResponse(self::json(200, '[]'));
        $store = new InMemoryTokenStore();
        $package = self::builder($http)->withTokenStore($store)->build();
        $query = ListEnrolments::firstPage(EnrolmentSamples::tenant());

        $package->listEnrolments()($query);
        $package->listEnrolments()($query);

        static::assertCount(1, $store->keys());
        static::assertCount(3, $http->getRequests());
    }

    public function testTheGivenLoggerAndOptionsReachTheHttpGateway(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));
        $logger = new CapturingLogger();
        $limiter = new RecordingRateLimiter();

        self::builder($http)
            ->withOptions(GatewayOptions::defaults()->withRateLimiter($limiter))
            ->withLogger($logger)
            ->build()
            ->listEnrolments()(ListEnrolments::firstPage(EnrolmentSamples::tenant()));

        $messages = array_map(static fn(array $record): string => $record['message'], $logger->records());
        static::assertSame(['URSSAF token request.', 'URSSAF call.'], $messages);
        static::assertNotSame([], $limiter->acquired());
    }

    public function testTheGivenClockDatesTheCachedToken(): void
    {
        $http = new Client();
        $http->addResponse(self::token());
        $http->addResponse(self::json(200, '[]'));
        $store = new InMemoryTokenStore();

        self::builder($http)
            ->withTokenStore($store)
            ->withClock(new FrozenClock(new DateTimeImmutable('2030-01-01T00:00:00Z')))
            ->build()
            ->listEnrolments()(ListEnrolments::firstPage(EnrolmentSamples::tenant()));

        $token = $store->get($store->keys()[0]);
        static::assertNotNull($token);
        static::assertSame('2030-01-01T01:00:00+00:00', $token->expiresAt()->format('c'));
    }

    public function testTheValidationPolicyReachesThePostingHandler(): void
    {
        $http = new Client();
        $package = self::builder($http)->withValidationPolicy(PostingValidationPolicy::default()->withPdfTeleworkRules())->build();
        $request = SyntheticPostings::withDetails(
            PostingDetails::of(null, null, null, Telework::withTelework(null, null, 2), null, null, null, null, null, [], null),
        );

        $this->expectException(ValidationFailed::class);
        try {
            $package->submitPosting()(SubmitPosting::of(EnrolmentSamples::tenant(), $request));
        } finally {
            static::assertSame([], $http->getRequests());
        }
    }

    public function testTheSimulatedGatewayReplacesTheHttpGatewayInTheSandbox(): void
    {
        $http = new Client();
        $simulator = SimulatedMobilityGateway::create();
        $package = self::builder($http)->withGateway($simulator)->build();
        $tenant = EnrolmentSamples::tenant();

        $enrolment = $package->requestEnrolment()(new RequestEnrolment($tenant, Siren::fromString('000000000'), null, EnrolmentCategory::Rg));
        $simulator->acceptEnrolment($enrolment->id());
        $receipt = $package->submitPosting()(SubmitPosting::afterCheckingEnrolment(
            $tenant,
            SyntheticPostings::withDetails(PostingDetails::of(null, null, null, null, null, null, null, null, null, [], null)),
            $enrolment->id(),
        ));

        static::assertSame($simulator, $package->gateway());
        static::assertSame([], $http->getRequests());
        static::assertSame('00000000-0000-4000-9000-000000000001', $receipt->first()->value());
    }

    public function testACustomGatewayIsRefusedInProduction(): void
    {
        $builder = self::builder(new Client(), Environment::Production);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A custom gateway is meant for tests and cannot be used with the production environment.');

        $builder->withGateway(SimulatedMobilityGateway::create());
    }

    public function testASwitchToProductionAfterACustomGatewayIsRefusedToo(): void
    {
        $builder = self::builder(new Client())->withGateway(SimulatedMobilityGateway::create());

        $this->expectException(LogicException::class);

        $builder->withEnvironment(Environment::Production);
    }

    public function testEveryOtherWithMethodKeepsTheCustomGateway(): void
    {
        $simulator = SimulatedMobilityGateway::create();
        $builder = self::builder(new Client())->withGateway($simulator);

        $rebuilt = $builder
            ->withBaseUri('https://example.test/api')
            ->withTokenUri('https://example.test/token')
            ->withTokenStore(new InMemoryTokenStore())
            ->withOptions(GatewayOptions::defaults())
            ->withValidationPolicy(PostingValidationPolicy::default())
            ->withEnvironment(Environment::Sandbox);

        static::assertSame($simulator, $rebuilt->build()->gateway());
    }

    private static function builder(Client $http, Environment $environment = Environment::Sandbox): UrssafMobiliteInternationaleBuilder
    {
        $factory = new Psr17Factory();

        return UrssafMobiliteInternationaleBuilder::create(
            new HttpTransport($http, $factory, $factory),
            new StaticCredentialsProvider(),
            $environment,
        );
    }

    private static function token(): Response
    {
        return self::json(200, json_encode(['access_token' => self::ISSUED, 'token_type' => 'Bearer', "expires_in" => 3600], JSON_THROW_ON_ERROR));
    }

    private static function json(int $status, string $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}

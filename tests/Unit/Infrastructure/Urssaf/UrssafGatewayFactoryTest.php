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

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafGatewayFactoryTest extends TestCase
{
    private const ANSWER = '{"access_token":"tok","expires_in":3600}';

    private function transport(Client $client): HttpTransport
    {
        $factory = new Psr17Factory();

        return new HttpTransport($client, $factory, $factory);
    }

    public function testCreateBuildsAWorkingGatewayWithTheDefaultOptions(): void
    {
        $client = new Client();
        $client->addResponse(new Response(200, [], self::ANSWER));
        $client->addResponse(new Response(200, [], '{"idEnrolement":"' . Calls::ENROLMENT_ID . '"}'));
        $tokens = new InMemoryTokenStore();

        $gateway = UrssafGatewayFactory::create(
            $this->transport($client),
            new StaticCredentialsProvider(),
            $tokens,
            ApiConfiguration::forEnvironment(Environment::Production),
        );
        $enrolment = $gateway->getEnrolment(Stacks::tenant(), EnrolmentId::fromString(Calls::ENROLMENT_ID));

        static::assertSame(Calls::ENROLMENT_ID, $enrolment->id()->value());
        $requests = $client->getRequests();
        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', (string) $requests[0]->getUri());
        static::assertSame(
            'https://api.urssaf.fr/tiersdecl/v1/mobilite/enrolements/' . Calls::ENROLMENT_ID,
            (string) $requests[1]->getUri(),
        );
        static::assertFalse($requests[1]->hasHeader('x-request-id'), 'No request id by default.');
        static::assertCount(1, $tokens->keys());
    }

    public function testTheDefaultRateLimiterNeverThrottles(): void
    {
        $client = new Client();
        $client->addResponse(new Response(200, [], self::ANSWER));
        $gateway = UrssafGatewayFactory::create(
            $this->transport($client),
            new StaticCredentialsProvider(),
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        $gateway->deleteEnrolment(Stacks::tenant(), EnrolmentId::fromString(Calls::ENROLMENT_ID));
        for ($i = 0; $i < Operation::PRODUCTION_QUOTA_PER_MINUTE + 5; ++$i) {
            $client->addResponse(new Response(200, [], ''));
            $gateway->deleteEnrolment(Stacks::tenant(), EnrolmentId::fromString(Calls::ENROLMENT_ID));
        }

        static::assertCount(Operation::PRODUCTION_QUOTA_PER_MINUTE + 7, $client->getRequests());
    }

    public function testEveryGivenOptionIsUsed(): void
    {
        $client = new Client();
        $client->addResponse(new Response(200, [], self::ANSWER));
        $client->addResponse(new Response(200, [], '[]'));
        $logger = new CapturingLogger();
        $clock = new SteppingClock(500_000);
        $tokens = new InMemoryTokenStore();

        UrssafGatewayFactory::createWith(
            $this->transport($client),
            new StaticCredentialsProvider(),
            $tokens,
            new ApiConfiguration(Environment::Sandbox, null, null, 'X-Request-Id'),
            GatewayOptions::defaults()->withLogger($logger)->withClock($clock)->withIdGenerator(new SequenceIdGenerator()),
        )->listEnrolments(Stacks::tenant(), 0, 20, null);

        $requests = $client->getRequests();
        static::assertSame('request-id-1', $requests[1]->getHeaderLine('X-Request-Id'));
        $records = $logger->records();
        static::assertCount(2, $records);
        static::assertSame(500, $records[1]['context']['duration_ms']);
        static::assertSame('request-id-1', $records[1]['context']['request_id']);
    }

    public function testAGivenRateLimiterIsConsultedAndStopsTheCall(): void
    {
        $client = new Client();

        $gateway = UrssafGatewayFactory::createWith(
            $this->transport($client),
            new StaticCredentialsProvider(),
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
            GatewayOptions::defaults()->withRateLimiter(new RejectingRateLimiter()),
        );

        $this->expectException(RateLimitExceeded::class);
        try {
            $gateway->deleteEnrolment(Stacks::tenant(), EnrolmentId::fromString(Calls::ENROLMENT_ID));
        } finally {
            static::assertSame([], $client->getRequests());
        }
    }
}

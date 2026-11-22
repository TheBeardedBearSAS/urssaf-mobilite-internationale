<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRequestIdTest extends TestCase
{
    public function testNoRequestIdIsGeneratedNorSentWithoutAConfiguredHeader(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);

        static::assertFalse($stack->lastRequest()->hasHeader('x-request-id'));
        static::assertSame('request-id-1', $stack->ids->generate(), 'The generator was not used.');
    }

    public function testAConfiguredRequestIdHeaderCarriesAFreshIdPerAttempt(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, null, 'X-Request-Id'));
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);

        $api = $stack->apiRequests();
        static::assertCount(2, $api);
        static::assertSame('request-id-1', $api[0]->getHeaderLine('X-Request-Id'));
        static::assertSame('request-id-2', $api[1]->getHeaderLine('X-Request-Id'));
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function operations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            yield $operation->value => [$operation];
        }
    }

    #[DataProvider('operations')]
    public function testTheRateLimiterIsConsultedBeforeEachAttemptWithTheTenantAndTheOperation(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        Calls::succeed($stack, $operation);

        Calls::run($stack, $operation);

        static::assertSame(['tenant-1:' . $operation->value], $stack->rateLimiter->acquired());
    }

    public function testTheTokenIsReusedAcrossCalls(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]');
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);
        Calls::run($stack, Operation::ListEnrolments);

        static::assertCount(3, $stack->requests());
        static::assertSame(StaticCredentialsProvider::CLIENT_ID, $stack->credentials->forTenant(Stacks::tenant())->clientId());
    }
}

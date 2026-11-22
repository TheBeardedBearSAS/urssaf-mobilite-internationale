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
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

final class JsonApiClientLoggingTest extends TestCase
{
    public function testItLogsMethodPathStatusAndDurationOnly(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);

        $records = $stack->logger->records();
        static::assertCount(1, $records);
        static::assertSame('info', $records[0]['level']);
        static::assertSame('URSSAF call.', $records[0]['message']);
        static::assertSame(
            ['method' => 'GET', 'path' => '/enrolements', 'status' => 200, 'duration_ms' => 250],
            $records[0]['context'],
        );
    }

    public function testItAddsTheRequestIdOnlyWhenAHeaderIsConfigured(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, null, 'X-Request-Id'));
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);

        static::assertSame(
            ['method' => 'GET', 'path' => '/enrolements', 'request_id' => 'request-id-1', 'status' => 200, 'duration_ms' => 0],
            $stack->logger->records()[0]['context'],
        );
    }

    public function testATransportFailureLogsTheExceptionClassAndNeverItsMessage(): void
    {
        $stack = Stacks::stepping(1_000_000);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('https://api-edi.urssaf.fr/x?secret=abc failed'));

        try {
            Calls::run($stack, Operation::GetEnrolment);
            static::fail('A failure was expected.');
        } catch (Throwable $exception) {
            static::assertStringNotContainsString('secret=abc', $exception->getMessage());
            static::assertNull($exception->getPrevious());
        }

        $records = $stack->logger->records();
        static::assertCount(1, $records);
        static::assertSame('warning', $records[0]['level']);
        static::assertSame('URSSAF call failed: transport error.', $records[0]['message']);
        static::assertSame(
            [
                'method' => 'GET',
                'path' => '/enrolements/' . Calls::ENROLMENT_ID,
                'duration_ms' => 1000,
                'exception' => TransportFailure::class,
            ],
            $records[0]['context'],
        );
        static::assertStringNotContainsString('secret=abc', $stack->logger->dump());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function answers(): iterable
    {
        yield 'rejection with error body' => [400, '[{"code":"LEAK-CODE","message":"LEAK-MESSAGE","description":"LEAK-DESCRIPTION"}]'];
        yield 'server error' => [500, '{"message":"LEAK-MESSAGE"}'];
        yield 'success body' => [200, '[{"id":"' . Calls::DOSSIER_ID . '"}]'];
    }

    #[DataProvider('answers')]
    public function testNoBodyQueryTokenNorSecretIsEverLogged(int $status, string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body);

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::post(Operation::SubmitPosting, '/declaration/detachement/UE', '{"nir":"LEAK-NIR"}'),
            );
        } catch (Throwable $exception) {
            static::assertStringNotContainsString('LEAK', $exception->getMessage());
        }

        $dump = $stack->logger->dump();
        static::assertStringNotContainsString('LEAK', $dump);
        static::assertStringNotContainsString(Stack::ISSUED, $dump);
        static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $dump);
        static::assertStringNotContainsString('Bearer', $dump);
        static::assertStringNotContainsString($body, $dump);
    }

    public function testTheQueryStringIsNeverLogged(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::ListEnrolments, '/enrolements', ['offset' => '0', 'limit' => '5', 'sort' => 'LEAK-SORT']),
        );

        static::assertStringNotContainsString('LEAK-SORT', $stack->logger->dump());
        static::assertStringNotContainsString('offset', $stack->logger->dump());
    }
}

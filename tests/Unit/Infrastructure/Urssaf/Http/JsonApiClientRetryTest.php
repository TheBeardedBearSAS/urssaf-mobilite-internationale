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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

/**
 * A 401 proves the call was not processed: the token is invalidated and the call replayed ONCE, on every operation
 * (the two POST included, ADR 0004). There is no other retry.
 */
final class JsonApiClientRetryTest extends TestCase
{
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
    public function testA401InvalidatesTheTokenAndReplaysTheCallOnce(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        Calls::succeed($stack, $operation);

        Calls::run($stack, $operation);

        $requests = $stack->requests();
        static::assertCount(4, $requests, 'token, call (401), token again, replayed call');
        static::assertSame('/api/oauth/v1/token', $requests[2]->getUri()->getPath());
        static::assertSame('Bearer ' . Stack::ISSUED, $requests[1]->getHeaderLine('Authorization'));
        static::assertSame('Bearer second-token', $requests[3]->getHeaderLine('Authorization'));
        static::assertSame((string) $requests[1]->getBody(), (string) $requests[3]->getBody());
        static::assertSame((string) $requests[1]->getUri(), (string) $requests[3]->getUri());
        static::assertCount(2, $stack->rateLimiter->acquired(), 'The rate limiter is consulted for each attempt.');
    }

    #[DataProvider('operations')]
    public function testASecond401FailsAuthenticationWithoutAThirdAttempt(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(401, '{"code":"expired"}');
        $stack->respondToken();
        $stack->respond(200, '{}');

        try {
            Calls::run($stack, $operation);
            static::fail('Authentication should fail.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }
        static::assertCount(2, $stack->apiRequests());
    }

    public function testATransportErrorOnTheReplayOfAPostIsAnUnknownOutcome(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->fail(new TransportFailure('connection reset'));

        try {
            Calls::run($stack, Operation::SubmitPosting);
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(TransportFailure::class, $exception->causeClass());
            static::assertNull($exception->httpStatus());
        }
    }

    public function testATransportErrorOnTheReplayOfAReadIsUnavailable(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->fail(new TransportFailure('connection reset'));

        $this->expectException(UrssafUnavailable::class);

        Calls::run($stack, Operation::GetEnrolment);
    }

    public function testATokenFailureOnTheReplayIsReportedAsAnAuthenticationFailure(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respond(401, '{"error":"invalid_client"}');

        try {
            Calls::run($stack, Operation::RequestEnrolment);
            static::fail('Authentication should fail.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }
        static::assertCount(1, $stack->apiRequests(), 'The call was not replayed without a token.');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function neverReplayed(): iterable
    {
        foreach ([400, 403, 404, 429, 500, 503] as $status) {
            yield 'HTTP ' . $status => [$status];
        }
    }

    #[DataProvider('neverReplayed')]
    public function testNothingButA401IsEverReplayed(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '');
        $stack->respond(200, '[]');

        try {
            Calls::run($stack, Operation::ListEnrolments);
            static::fail('A failure was expected.');
        } catch (Throwable) {
            static::assertCount(1, $stack->apiRequests());
        }
    }

    #[DataProvider('operations')]
    public function testATransportErrorIsNeverRetried(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));
        Calls::succeed($stack, $operation);

        try {
            Calls::run($stack, $operation);
            static::fail('A failure was expected.');
        } catch (OutcomeUnknown|UrssafUnavailable $exception) {
            static::assertSame($operation->isUnsafeWrite(), $exception instanceof OutcomeUnknown);
            static::assertTrue($exception->mayHaveReachedUrssaf());
        }
        static::assertCount(1, $stack->apiRequests());
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Exceptions;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

/**
 * The status matrix of api-reference §2 / ADR 0002 / ADR 0004, for every operation.
 */
final class JsonApiClientAvailabilityTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, int, ?int, string}>
     */
    public static function quotaCases(): iterable
    {
        foreach (Operation::cases() as $operation) {
            yield $operation->value . ' with delay' => [$operation, 429, 30, '30'];
            yield $operation->value . ' without header' => [$operation, 429, null, ''];
            yield $operation->value . ' http date' => [$operation, 429, null, 'Wed, 21 Oct 2026 07:28:00 GMT'];
            yield $operation->value . ' garbage' => [$operation, 429, null, 'soon'];
        }
    }

    #[DataProvider('quotaCases')]
    public function testA429IsAQuotaFailureOnEveryOperationThatWasNotProcessed(
        Operation $operation,
        int $status,
        ?int $retryAfter,
        string $header,
    ): void {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '', $header === '' ? [] : ['Retry-After' => $header]);

        try {
            Calls::run($stack, $operation);
            static::fail('A quota failure was expected.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->isQuotaExceeded());
            static::assertTrue(Exceptions::isRetryable($exception));
            static::assertSame(429, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame($operation, $exception->operation());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
        static::assertCount(1, $stack->apiRequests());
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function nonPostOperations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            if ($operation->isUnsafeWrite()) {
                continue;
            }

            yield $operation->value => [$operation];
        }
    }

    #[DataProvider('nonPostOperations')]
    public function testA5xxOnAReadOrADeletionIsUnavailableAndMayHaveReachedUrssaf(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(503, '{"code":"down"}', ['Retry-After' => '12']);

        try {
            Calls::run($stack, $operation);
            static::fail('An unavailable failure was expected.');
        } catch (UrssafUnavailable $exception) {
            static::assertFalse($exception->isQuotaExceeded());
            static::assertSame(503, $exception->httpStatus());
            static::assertSame(12, $exception->retryAfterSeconds());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            static::assertTrue($exception->errors()->hasCode('down'));
            static::assertSame($operation, $exception->operation());
        }
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function postOperations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            if (!$operation->isUnsafeWrite()) {
                continue;
            }

            yield $operation->value => [$operation];
        }
    }

    #[DataProvider('postOperations')]
    public function testA5xxOnAPostIsAnUnknownOutcomeThatIsNotRetryable(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(500, '{"code":"boom"}');

        try {
            Calls::run($stack, $operation);
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertFalse(Exceptions::isRetryable($exception));
            static::assertSame(500, $exception->httpStatus());
            static::assertSame($operation, $exception->operation());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            static::assertNull($exception->causeClass());
        }
        static::assertCount(1, $stack->apiRequests(), 'A POST is never resent.');
    }
}

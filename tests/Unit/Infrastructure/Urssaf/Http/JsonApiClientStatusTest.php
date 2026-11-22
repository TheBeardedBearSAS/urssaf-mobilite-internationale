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
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\StatusMatrix;
use Throwable;

/**
 * The status matrix of api-reference §2 / ADR 0002 / ADR 0004, for every operation.
 */
final class JsonApiClientStatusTest extends TestCase
{
    /**
     * @param class-string<Throwable> $class
     */
    #[DataProviderExternal(StatusMatrix::class, 'failures')]
    public function testEveryNonSuccessStatusMapsToTheDocumentedFailure(Operation $operation, int $status, string $class): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '{"code":"c","message":"m","description":"d"}');
        // A 401 is replayed once: the second answer is the same.
        $stack->respondToken();
        $stack->respond($status, '{"code":"c","message":"m","description":"d"}');

        try {
            Calls::run($stack, $operation);
            static::fail('A failure was expected for HTTP ' . $status);
        } catch (Throwable $exception) {
            static::assertInstanceOf($class, $exception);
        }
    }

    /**
     * @return iterable<string, array{Operation, int}>
     */
    public static function undeclaredSuccesses(): iterable
    {
        foreach (Operation::cases() as $operation) {
            $request = Calls::request($operation);
            for ($status = 200; $status < 300; ++$status) {
                if ($request->declares($status)) {
                    continue;
                }

                yield $operation->value . ' ' . $status => [$operation, $status];
            }
        }
    }

    #[DataProvider('undeclaredSuccesses')]
    public function testAnUndeclaredSuccessIsAnUnknownOutcomeOnPostAndUnexpectedElsewhere(Operation $operation, int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '[{"id":"' . Calls::DOSSIER_ID . '"}]');

        try {
            Calls::run($stack, $operation);
            static::fail('An undeclared success must be refused.');
        } catch (OutcomeUnknown $exception) {
            static::assertTrue($operation->isUnsafeWrite());
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($operation, $exception->operation());
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertFalse($operation->isUnsafeWrite());
            static::assertSame($status, $exception->getCode());
        }
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
    public function testTheDeclaredSuccessIsAccepted(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        Calls::succeed($stack, $operation);

        Calls::run($stack, $operation);

        static::assertCount(1, $stack->apiRequests());
    }
}

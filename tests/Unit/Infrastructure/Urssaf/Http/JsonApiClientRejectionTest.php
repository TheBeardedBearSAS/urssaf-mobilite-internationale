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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Exceptions;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

/**
 * The status matrix of api-reference §2 / ADR 0002 / ADR 0004, for every operation.
 */
final class JsonApiClientRejectionTest extends TestCase
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
    public function testA400IsARejectionThatKeepsTheStatusAndTheOperation(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '[{"code":"A","message":"B","description":"C"},{"code":"D"}]');

        try {
            Calls::run($stack, $operation);
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertSame($operation, $exception->operation());
            static::assertCount(2, $exception->errors());
            static::assertTrue($exception->hasErrorCode('A'));
            static::assertFalse(Exceptions::isRetryable($exception));
        }
    }

    public function testTheDeclarationForbiddenIsABusinessRefusalWithItsErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(403, '{"code":"X","message":"not authorised"}');

        try {
            Calls::run($stack, Operation::SubmitPosting);
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(403, $exception->httpStatus());
            static::assertSame(Operation::SubmitPosting, $exception->operation());
            static::assertTrue($exception->hasErrorCode('X'));
        }
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function operationsWithANotFound(): iterable
    {
        foreach (Operation::cases() as $operation) {
            if ($operation === Operation::SubmitPosting) {
                continue;
            }

            yield $operation->value => [$operation];
        }
    }

    #[DataProvider('operationsWithANotFound')]
    public function testA404IsAResourceNotFoundThatKeepsTheErrors(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(404, '{"code":"nf","message":"not found"}');

        try {
            Calls::run($stack, $operation);
            static::fail('A not found was expected.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame($operation, $exception->operation());
            static::assertSame(404, $exception->httpStatus());
            static::assertTrue($exception->errors()->hasCode('nf'));
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function errorBodies(): iterable
    {
        yield 'object' => ['{"code":"A","message":"B","description":"C"}', 1];
        yield 'list' => ['[{"code":"A"},{"message":"B"},{"description":"C"}]', 3];
        yield 'empty body' => ['', 0];
        yield 'html' => ['<html>gateway error</html>', 0];
        yield 'list with a scalar' => ['[{"code":"A"}, 3]', 0];
        yield 'unknown members' => ['{"foo":"bar"}', 0];
    }

    #[DataProvider('errorBodies')]
    public function testErrorBodiesAreParsedLeniently(string $body, int $count): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, $body);

        try {
            Calls::run($stack, Operation::SubmitPosting);
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertCount($count, $exception->errors());
        }
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientUnreadableTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'html' => ['<html>LEAK</html>'];
        yield 'scalar' => ['"LEAK"'];
        yield 'truncated' => ['[{"id":"LEAK'];
    }

    #[DataProvider('unreadableBodies')]
    public function testAnUnreadableDeclaredAnswerOfAPostIsAnUnknownOutcome(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, $body);

        try {
            Calls::run($stack, Operation::SubmitPosting);
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(200, $exception->httpStatus());
            static::assertStringNotContainsString('LEAK', $exception->getMessage());
        }
    }

    #[DataProvider('unreadableBodies')]
    public function testAnUnreadableDeclaredAnswerOfARequestEnrolmentIsAnUnknownOutcome(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(201, $body);

        $this->expectException(OutcomeUnknown::class);

        Calls::run($stack, Operation::RequestEnrolment);
    }

    #[DataProvider('unreadableBodies')]
    public function testAnUnreadableDeclaredAnswerOfAReadIsAnUnexpectedResponse(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, $body);

        try {
            Calls::run($stack, Operation::GetEnrolment);
            static::fail('An unexpected response was expected.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertStringNotContainsString('LEAK', $exception->getMessage());
        }
    }

    #[DataProvider('unreadableBodies')]
    public function testTheBodyOfADeletionIsNeverRead(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, $body);

        Calls::run($stack, Operation::DeleteEnrolment);

        static::assertCount(1, $stack->apiRequests());
    }
}

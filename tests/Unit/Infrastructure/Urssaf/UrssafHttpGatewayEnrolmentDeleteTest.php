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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

final class UrssafHttpGatewayEnrolmentDeleteTest extends TestCase
{
    private const ID = Calls::ENROLMENT_ID;

    public function testGetEnrolmentNotFoundKeepsTheErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(404, '{"code":"unknown"}');

        try {
            $stack->gateway()->getEnrolment(Stacks::tenant(), EnrolmentId::fromString(self::ID));
            static::fail('A not found was expected.');
        } catch (UrssafResourceNotFound $exception) {
            static::assertSame(Operation::GetEnrolment, $exception->operation());
            static::assertTrue($exception->errors()->hasCode('unknown'));
        }
    }

    public function testDeleteEnrolmentAcceptsAnEmptyOk(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '');

        $stack->gateway()->deleteEnrolment(Stacks::tenant(), EnrolmentId::fromString(self::ID));

        $request = $stack->lastRequest();
        static::assertSame('DELETE', $request->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/enrolements/' . self::ID, $request->getUri()->getPath());
    }

    public function testDeleteEnrolmentFailuresKeepTheirMeaning(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(500, '');
        $stack->fail(new TransportFailure('reset'));
        $stack->respond(404, '');
        $stack->respond(403, '');
        $gateway = $stack->gateway();
        $id = EnrolmentId::fromString(self::ID);

        foreach ([UrssafUnavailable::class, UrssafUnavailable::class, UrssafResourceNotFound::class, UrssafAuthenticationFailed::class] as $expected) {
            try {
                $gateway->deleteEnrolment(Stacks::tenant(), $id);
                static::fail('A failure was expected.');
            } catch (Throwable $exception) {
                static::assertInstanceOf($expected, $exception);
                if ($exception instanceof UrssafUnavailable) {
                    static::assertTrue($exception->mayHaveReachedUrssaf());
                }
            }
        }
    }
}

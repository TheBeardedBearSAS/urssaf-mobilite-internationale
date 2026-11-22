<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Exceptions;

final class UnexpectedUrssafResponseTest extends TestCase
{
    public function testItIsAnUrssafExceptionThatIsNotRetryable(): void
    {
        $exception = UnexpectedUrssafResponse::invalidPayload('field "x" is missing');

        static::assertTrue(Exceptions::isUrssafException($exception));
        static::assertFalse(Exceptions::isRetryable($exception));
        static::assertSame('Unexpected URSSAF response: field "x" is missing.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testItKeepsThePreviousException(): void
    {
        $previous = new RuntimeException('cause');

        $exception = UnexpectedUrssafResponse::causedBy('the body is unreadable', $previous);

        static::assertSame('Unexpected URSSAF response: the body is unreadable.', $exception->getMessage());
        static::assertSame($previous, $exception->getPrevious());
        static::assertSame(0, $exception->getCode());
    }

    public function testTheStatusVariantNamesTheOperationAndTheStatus(): void
    {
        $exception = UnexpectedUrssafResponse::unexpectedStatus(Operation::GetCertificate, 302);

        static::assertSame('Unexpected URSSAF response for operation get-certificat: HTTP 302.', $exception->getMessage());
        static::assertSame(302, $exception->getCode());
    }
}

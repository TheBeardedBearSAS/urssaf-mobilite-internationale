<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

final class UrssafExceptionsTest extends TestCase
{
    public function testRequestRejected(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'libelle', 'detail'), ApiError::of('ERR_B', 'm', 'd'));

        $exception = UrssafRequestRejected::withErrors(Operation::RequestEnrolment, 400, $errors);

        static::assertSame('URSSAF operation post-enrolement rejected the request (HTTP 400).', $exception->getMessage());
        static::assertSame(Operation::RequestEnrolment, $exception->operation());
        static::assertSame(400, $exception->httpStatus());
        static::assertSame(400, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->hasErrorCode('ERR_B'));
        static::assertFalse($exception->hasErrorCode('ERR_C'));
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testPostingForbiddenIsARejection(): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::SubmitPosting, 403, ApiErrors::none());

        static::assertSame('URSSAF operation post-declaration-grands-comptes rejected the request (HTTP 403).', $exception->getMessage());
        static::assertSame(403, $exception->httpStatus());
        static::assertTrue($exception->errors()->isEmpty());
    }

    public function testResourceNotFound(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'libelle', 'detail'));

        $exception = UrssafResourceNotFound::withErrors(Operation::GetEnrolment, $errors);

        static::assertSame('URSSAF operation get-enrolement found no such resource (HTTP 404).', $exception->getMessage());
        static::assertSame(404, $exception->getCode());
        static::assertSame(404, $exception->httpStatus());
        static::assertSame(Operation::GetEnrolment, $exception->operation());
        static::assertSame($errors, $exception->errors());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testAuthenticationFailed(): void
    {
        $exception = UrssafAuthenticationFailed::withStatus(401);

        static::assertSame('URSSAF authentication or authorisation failed (HTTP 401).', $exception->getMessage());
        static::assertSame(401, $exception->httpStatus());
        static::assertSame(401, $exception->getCode());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }
}

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
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

final class UrssafUnavailableTest extends TestCase
{
    public function testServerError(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafUnavailable::withStatus(Operation::DeleteEnrolment, 500, 30, $errors);

        static::assertContains(RetryableException::class, class_implements($exception));
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertSame('URSSAF operation delete-enrolement is temporarily unavailable (HTTP 500).', $exception->getMessage());
        static::assertSame(Operation::DeleteEnrolment, $exception->operation());
        static::assertSame(500, $exception->httpStatus());
        static::assertSame(500, $exception->getCode());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertNull($exception->causeClass());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testStatusClassification(): void
    {
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetEnrolment, 499, null, ApiErrors::none())->mayHaveReachedUrssaf());
        static::assertTrue(UrssafUnavailable::withStatus(Operation::GetEnrolment, 500, null, ApiErrors::none())->mayHaveReachedUrssaf());
        static::assertNull(UrssafUnavailable::withStatus(Operation::GetEnrolment, 503, null, ApiErrors::none())->retryAfterSeconds());
        static::assertTrue(UrssafUnavailable::withStatus(Operation::GetEnrolment, 429, null, ApiErrors::none())->isQuotaExceeded());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetEnrolment, 428, null, ApiErrors::none())->isQuotaExceeded());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::GetEnrolment, 430, null, ApiErrors::none())->isQuotaExceeded());
    }

    public function testQuotaExceeded(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafUnavailable::quotaExceeded(Operation::SubmitPosting, 12, $errors);

        static::assertSame('URSSAF quota exceeded for operation post-declaration-grands-comptes (HTTP 429).', $exception->getMessage());
        static::assertSame(Operation::SubmitPosting, $exception->operation());
        static::assertTrue($exception->isQuotaExceeded());
        static::assertSame(429, $exception->httpStatus());
        static::assertSame(429, $exception->getCode());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertNull($exception->causeClass());
        static::assertSame($errors, $exception->errors());
        static::assertNull(UrssafUnavailable::quotaExceeded(Operation::GetCertificate, null, ApiErrors::none())->retryAfterSeconds());
    }

    public function testTransportError(): void
    {
        $exception = UrssafUnavailable::dueToTransportError(Operation::ListEnrolments, RuntimeException::class);

        static::assertSame('URSSAF operation get-enrolement-list could not be reached (RuntimeException).', $exception->getMessage());
        static::assertSame(Operation::ListEnrolments, $exception->operation());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertSame(RuntimeException::class, $exception->causeClass());
        static::assertNull($exception->getPrevious());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testWhileAuthenticating(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'm', 'd'));

        $exception = UrssafUnavailable::whileAuthenticating(Operation::SubmitPosting, 503, 12, null, $errors);

        static::assertSame('URSSAF authentication for operation post-declaration-grands-comptes is temporarily unavailable.', $exception->getMessage());
        static::assertSame(Operation::SubmitPosting, $exception->operation());
        static::assertSame(503, $exception->httpStatus());
        static::assertSame(503, $exception->getCode());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertNull($exception->causeClass());
        static::assertSame($errors, $exception->errors());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertTrue(UrssafUnavailable::whileAuthenticating(Operation::SubmitPosting, 429, null, null, ApiErrors::none())->isQuotaExceeded());

        $transport = UrssafUnavailable::whileAuthenticating(Operation::SubmitPosting, null, null, RuntimeException::class, ApiErrors::none());
        static::assertNull($transport->httpStatus());
        static::assertSame(0, $transport->getCode());
        static::assertSame(RuntimeException::class, $transport->causeClass());
        static::assertTrue($transport->errors()->isEmpty());
        static::assertFalse($transport->mayHaveReachedUrssaf());
    }
}

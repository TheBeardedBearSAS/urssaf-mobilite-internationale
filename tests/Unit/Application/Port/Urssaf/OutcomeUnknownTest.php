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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

final class OutcomeUnknownTest extends TestCase
{
    private const POSTING_ADVICE = 'the posting may have been recorded; do not resend it before checking with URSSAF outside the API (case manager, urssaf.fr).';

    public function testAfterTransportError(): void
    {
        $exception = OutcomeUnknown::afterTransportError(Operation::SubmitPosting, RuntimeException::class);

        static::assertSame('URSSAF operation post-declaration-grands-comptes failed with RuntimeException: ' . self::POSTING_ADVICE, $exception->getMessage());
        static::assertSame(Operation::SubmitPosting, $exception->operation());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertSame(RuntimeException::class, $exception->causeClass());
        static::assertTrue($exception->mayHaveReachedUrssaf());
        static::assertNull($exception->getPrevious());
    }

    public function testAfterStatus(): void
    {
        $exception = OutcomeUnknown::afterStatus(Operation::SubmitPosting, 502);

        static::assertSame('URSSAF operation post-declaration-grands-comptes answered HTTP 502: ' . self::POSTING_ADVICE, $exception->getMessage());
        static::assertSame(Operation::SubmitPosting, $exception->operation());
        static::assertSame(502, $exception->httpStatus());
        static::assertSame(502, $exception->getCode());
        static::assertNull($exception->causeClass());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }

    public function testUnreadableAnswer(): void
    {
        $exception = OutcomeUnknown::unreadableAnswer(Operation::RequestEnrolment, 201, 'missing idEnrolement');

        static::assertSame(
            'URSSAF operation post-enrolement answered HTTP 201 with an unreadable response (missing idEnrolement): the enrolment may have been recorded; list the enrolments before resending it.',
            $exception->getMessage(),
        );
        static::assertSame(Operation::RequestEnrolment, $exception->operation());
        static::assertSame(201, $exception->httpStatus());
        static::assertSame(201, $exception->getCode());
        static::assertNull($exception->causeClass());
        static::assertTrue($exception->mayHaveReachedUrssaf());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function provideAdvices(): iterable
    {
        yield 'request enrolment' => [Operation::RequestEnrolment, 'the enrolment may have been recorded; list the enrolments before resending it.'];
        yield 'submit posting' => [Operation::SubmitPosting, self::POSTING_ADVICE];
        $generic = 'the call may have been processed; check its effect before resending it.';
        yield 'list enrolments' => [Operation::ListEnrolments, $generic];
        yield 'get enrolment' => [Operation::GetEnrolment, $generic];
        yield 'delete enrolment' => [Operation::DeleteEnrolment, $generic];
        yield 'get certificate' => [Operation::GetCertificate, $generic];
    }

    #[DataProvider('provideAdvices')]
    public function testAdvicePerOperation(Operation $operation, string $advice): void
    {
        static::assertStringEndsWith(': ' . $advice, OutcomeUnknown::afterStatus($operation, 500)->getMessage());
    }
}

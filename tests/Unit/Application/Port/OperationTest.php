<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;

final class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, string, bool}>
     */
    public static function provideOperations(): iterable
    {
        yield 'request enrolment' => [Operation::RequestEnrolment, 'post-enrolement', true];
        yield 'list enrolments' => [Operation::ListEnrolments, 'get-enrolement-list', false];
        yield 'get enrolment' => [Operation::GetEnrolment, 'get-enrolement', false];
        yield 'delete enrolment' => [Operation::DeleteEnrolment, 'delete-enrolement', false];
        yield 'submit posting' => [Operation::SubmitPosting, 'post-declaration-grands-comptes', true];
        yield 'get certificate' => [Operation::GetCertificate, 'get-certificat', false];
    }

    #[DataProvider('provideOperations')]
    public function testOperationIdsQuotaAndUnsafeWrites(Operation $operation, string $operationId, bool $unsafeWrite): void
    {
        static::assertSame($operationId, $operation->value);
        static::assertSame(200, $operation->quotaPerMinute());
        static::assertSame($unsafeWrite, $operation->isUnsafeWrite());
    }

    public function testThereAreSixOperations(): void
    {
        static::assertCount(6, Operation::cases());
    }
}

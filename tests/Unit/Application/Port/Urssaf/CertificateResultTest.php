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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;

final class CertificateResultTest extends TestCase
{
    public function testAvailable(): void
    {
        $file = new DownloadedFile(new InMemoryBinaryStream('%PDF-synthetic'), 'application/pdf', null);

        $result = CertificateAvailable::of($file);

        static::assertContains(CertificateResult::class, class_implements($result));
        static::assertSame($file, $result->file());
        static::assertSame(200, $result->httpStatus());
        static::assertStringNotContainsString('%PDF', print_r($result, true));
    }

    public function testInProgressWithoutBody(): void
    {
        $errors = ApiErrors::none();

        $result = CertificateInProgress::of(204, $errors);

        static::assertContains(CertificateResult::class, class_implements($result));
        static::assertSame(204, $result->httpStatus());
        static::assertSame($errors, $result->errors());
    }

    public function testInProgressKeepsTheRawBadRequest(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_FICTIF', 'm', 'd'));

        $result = CertificateInProgress::of(400, $errors);

        static::assertSame(400, $result->httpStatus());
        static::assertSame($errors, $result->errors());
    }

    public function testRefusedOrUnknown(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_FICTIF', 'm', 'd'));

        $result = CertificateRefusedOrUnknown::of(404, $errors);

        static::assertContains(CertificateResult::class, class_implements($result));
        static::assertSame(404, $result->httpStatus());
        static::assertSame($errors, $result->errors());
    }
}

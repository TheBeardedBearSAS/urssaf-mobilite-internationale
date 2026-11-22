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

final class ApiErrorsTest extends TestCase
{
    public function testApiError(): void
    {
        $error = ApiError::of('ERR_FICTIF', 'Libelle fictif.', 'Description fictive.');

        static::assertSame('ERR_FICTIF', $error->code());
        static::assertSame('Libelle fictif.', $error->message());
        static::assertSame('Description fictive.', $error->description());
        static::assertTrue($error->hasCode('ERR_FICTIF'));
        static::assertFalse($error->hasCode('ERR_AUTRE'));
        static::assertTrue($error->equals(ApiError::of('ERR_FICTIF', 'Libelle fictif.', 'Description fictive.')));
        static::assertFalse($error->equals(ApiError::of('X', 'Libelle fictif.', 'Description fictive.')));
        static::assertFalse($error->equals(ApiError::of('ERR_FICTIF', 'X', 'Description fictive.')));
        static::assertFalse($error->equals(ApiError::of('ERR_FICTIF', 'Libelle fictif.', 'X')));
    }

    public function testApiErrorFieldsAreNullable(): void
    {
        $error = ApiError::of(null, null, null);

        static::assertNull($error->code());
        static::assertNull($error->message());
        static::assertNull($error->description());
        static::assertFalse($error->hasCode(''));
        static::assertTrue($error->equals(ApiError::of(null, null, null)));
        static::assertFalse($error->equals(ApiError::of('', null, null)));
        static::assertFalse($error->equals(ApiError::of(null, '', null)));
        static::assertFalse($error->equals(ApiError::of(null, null, '')));
    }

    public function testCollection(): void
    {
        $first = ApiError::of('ERR_A', 'm', 'd');
        $withoutCode = ApiError::of(null, 'm', 'd');
        $second = ApiError::of('ERR_B', 'm', 'd');

        $errors = ApiErrors::of($first, $withoutCode, $second);

        static::assertFalse($errors->isEmpty());
        static::assertCount(3, $errors);
        static::assertSame([$first, $withoutCode, $second], $errors->all());
        static::assertSame(['ERR_A', 'ERR_B'], $errors->codes());
        static::assertTrue($errors->hasCode('ERR_A'));
        static::assertTrue($errors->hasCode('ERR_B'));
        static::assertFalse($errors->hasCode('ERR_C'));
    }

    public function testEmptyCollections(): void
    {
        static::assertTrue(ApiErrors::none()->isEmpty());
        static::assertCount(0, ApiErrors::none());
        static::assertSame([], ApiErrors::none()->all());
        static::assertSame([], ApiErrors::none()->codes());
        static::assertTrue(ApiErrors::of()->isEmpty());
        static::assertFalse(ApiErrors::none()->hasCode('X'));
    }

    public function testOfNormalisesKeys(): void
    {
        $error = ApiError::of('X', 'm', 'd');

        static::assertSame([$error], ApiErrors::of(...['named' => $error])->all());
    }
}

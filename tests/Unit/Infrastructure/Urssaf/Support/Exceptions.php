<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

/**
 * Marker checks on a thrown exception, kept out of the tests so that they are not statically known.
 */
final class Exceptions
{
    private function __construct() {}

    public static function isRetryable(object $exception): bool
    {
        return \array_key_exists(RetryableException::class, class_implements($exception));
    }

    public static function isUrssafException(object $exception): bool
    {
        return \array_key_exists(UrssafException::class, class_implements($exception));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting;

use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\DomainException;

final class InvalidValueAssertions
{
    /**
     * Asserts the code and parameters of the domain exception, and that its message does not repeat the input.
     *
     * @param callable(): mixed     $build
     * @param array<string, scalar> $params
     *
     * @param-immediately-invoked-callable $build
     */
    public static function assertThrows(callable $build, string $code, array $params = [], string $input = ''): void
    {
        try {
            $build();
            Assert::fail('DomainException expected.');
        } catch (DomainException $exception) {
            Assert::assertSame($code, $exception->violationCode());
            Assert::assertSame($params, $exception->params());
            if ($input !== '') {
                Assert::assertStringNotContainsString($input, $exception->getMessage());
            }
        }
    }
}

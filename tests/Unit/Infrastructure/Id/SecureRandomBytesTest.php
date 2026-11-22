<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Id;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Id\SecureRandomBytes;

final class SecureRandomBytesTest extends TestCase
{
    public function testReturnsTheRequestedNumberOfBytes(): void
    {
        $source = new SecureRandomBytes();

        static::assertSame(16, \strlen($source->bytes(16)));
        static::assertSame(3, \strlen($source->bytes(3)));
    }

    public function testBytesAreNotConstant(): void
    {
        $source = new SecureRandomBytes();

        static::assertNotSame($source->bytes(16), $source->bytes(16));
    }
}

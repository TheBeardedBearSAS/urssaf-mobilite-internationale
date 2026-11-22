<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

final class TenantIdTest extends TestCase
{
    public function testFromString(): void
    {
        $tenant = TenantId::fromString('acme');

        static::assertSame('acme', $tenant->value());
        static::assertSame('acme', (string) $tenant);
        static::assertTrue($tenant->equals(TenantId::fromString('acme')));
        static::assertFalse($tenant->equals(TenantId::fromString('other')));
        static::assertSame(str_repeat('a', 64), TenantId::fromString(str_repeat('a', 64))->value());
    }

    public function testCountsCharactersNotBytes(): void
    {
        static::assertSame(64, mb_strlen(TenantId::fromString(str_repeat('é', 64))->value()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty' => ['', 'tenant_id.empty'];
        yield 'blank' => ["  \t", 'tenant_id.empty'];
        yield 'too long' => [str_repeat('a', 65), 'tenant_id.too_long'];
        yield 'too long multibyte' => [str_repeat('é', 65), 'tenant_id.too_long'];
    }

    #[DataProvider('provideInvalid')]
    public function testInvalid(string $value, string $code): void
    {
        try {
            TenantId::fromString($value);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame($code, $invalidValue->violationCode());
        }
    }
}

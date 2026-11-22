<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonObject;

final class JsonEncoderTest extends TestCase
{
    public function testItEncodesObjectsAndListsCompactly(): void
    {
        static::assertSame('{"a":1,"b":[true,null,"x"],"c":{"d":false}}', JsonEncoder::encode([
            'a' => 1,
            'b' => [true, null, 'x'],
            'c' => ['d' => false],
        ]));
        static::assertSame('[]', JsonEncoder::encode([]));
    }

    public function testItLeavesSlashesAndUnicodeUnescaped(): void
    {
        static::assertSame('{"k":"é/à"}', JsonEncoder::encode(['k' => 'é/à']));
    }

    public function testAJsonObjectIsAnObjectEvenWhenEmptyAndAtAnyDepth(): void
    {
        static::assertSame('{}', JsonEncoder::encode(JsonObject::of([])));
        static::assertSame('{"a":{},"b":{"c":1}}', JsonEncoder::encode(['a' => JsonObject::of([]), 'b' => JsonObject::of(['c' => 1])]));
        static::assertSame(['c' => 1], JsonObject::of(['c' => 1])->members());
    }

    public function testItRefusesInvalidUtf8(): void
    {
        try {
            JsonEncoder::encode(['k' => "secret-\xB1\x31"]);
            static::fail('An InvalidValue was expected.');
        } catch (InvalidValue $exception) {
            static::assertSame('payload.invalid_encoding', $exception->violationCode());
            static::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }
}

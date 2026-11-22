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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class PayloadEntriesTest extends TestCase
{
    public function testItReadsTheEntriesOfAList(): void
    {
        $entries = JsonDecoder::decode('[{"a": "1"}, {}, {"a": "3"}]')->entries();

        static::assertCount(3, $entries);
        static::assertSame('1', $entries[0]->string('a'));
        static::assertNull($entries[1]->string('a'));
        static::assertSame('3', $entries[2]->string('a'));
        static::assertSame([], JsonDecoder::decode('[]')->entries());
    }

    public function testItRefusesEntriesOfAnObject(): void
    {
        try {
            JsonDecoder::decode('{"a": {}}')->entries();
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame('expected a JSON array', $exception->reason());
        }
    }

    #[DataProvider('listsOfNonObjects')]
    public function testItRefusesAListThatHoldsNonObjects(string $json): void
    {
        try {
            JsonDecoder::decode($json)->entries();
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame('expected a JSON array of objects', $exception->reason());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function listsOfNonObjects(): iterable
    {
        yield 'string' => ['["a"]'];
        yield 'number' => ['[1]'];
        yield 'null' => ['[null]'];
        yield 'nested list' => ['[[1]]'];
        yield 'object then scalar' => ['[{"a": "b"}, 2]'];
    }
}

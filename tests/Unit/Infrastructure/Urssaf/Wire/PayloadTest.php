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
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class PayloadTest extends TestCase
{
    public function testItAcceptsAnArrayAndRefusesScalars(): void
    {
        static::assertTrue(Payload::fromDecoded([])->isList());

        $this->expectException(UnreadablePayload::class);
        $this->expectExceptionMessage('Unreadable URSSAF payload: body is neither a JSON object nor a JSON array.');
        Payload::fromDecoded('secret-value');
    }

    #[DataProvider('shapes')]
    public function testItTellsListsFromObjects(string $json, bool $isList): void
    {
        static::assertSame($isList, JsonDecoder::decode($json)->isList());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function shapes(): iterable
    {
        yield 'empty array' => ['[]', true];
        yield 'empty object' => ['{}', true];
        yield 'list' => ['[1, 2]', true];
        yield 'object' => ['{"a": 1}', false];
        yield 'sparse keys' => ['{"1": "a"}', false];
    }

    public function testItReadsStringsDefensively(): void
    {
        $payload = JsonDecoder::decode('{"s": "x", "i": 3, "n": null, "b": true, "a": ["x"]}');

        static::assertSame('x', $payload->string('s'));
        static::assertNull($payload->string('i'));
        static::assertNull($payload->string('n'));
        static::assertNull($payload->string('b'));
        static::assertNull($payload->string('a'));
        static::assertNull($payload->string('absent'));
        static::assertSame('', JsonDecoder::decode('{"s": ""}')->string('s'));
    }

    public function testItRequiresANonEmptyString(): void
    {
        static::assertSame('x', JsonDecoder::decode('{"s": "x"}')->requireString('s'));
    }

    #[DataProvider('invalidRequiredStrings')]
    public function testItRefusesAMissingOrEmptyRequiredStringWithoutRevealingTheValue(string $json): void
    {
        try {
            JsonDecoder::decode($json)->requireString('id');
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame('field "id" is missing or empty', $exception->reason());
            static::assertSame('Unreadable URSSAF payload: field "id" is missing or empty.', $exception->getMessage());
            static::assertNull($exception->getPrevious());
            static::assertSame(0, $exception->getCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRequiredStrings(): iterable
    {
        yield 'absent' => ['{"other": "secret"}'];
        yield 'empty' => ['{"id": ""}'];
        yield 'number' => ['{"id": 12}'];
        yield 'null' => ['{"id": null}'];
    }
}

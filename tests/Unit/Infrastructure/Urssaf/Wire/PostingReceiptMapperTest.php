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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingReceiptMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class PostingReceiptMapperTest extends TestCase
{
    public function testItReadsOneIdentifier(): void
    {
        $receipt = (new PostingReceiptMapper())->fromPayload(JsonDecoder::decode('[{"id": "0B9F3F0E-0000-4000-8000-0000000000A1"}]'));

        static::assertCount(1, $receipt->dossierIds());
        static::assertSame('0b9f3f0e-0000-4000-8000-0000000000a1', $receipt->first()->value());
    }

    public function testItKeepsEveryIdentifierInOrder(): void
    {
        $receipt = (new PostingReceiptMapper())->fromPayload(JsonDecoder::decode(
            '[{"id": "0b9f3f0e-0000-4000-8000-0000000000a1"}, {"id": "0b9f3f0e-0000-4000-8000-0000000000a2", "extra": 1}]',
        ));

        static::assertSame(
            ['0b9f3f0e-0000-4000-8000-0000000000a1', '0b9f3f0e-0000-4000-8000-0000000000a2'],
            array_map(static fn(DossierId $id): string => $id->value(), $receipt->dossierIds()),
        );
    }

    #[DataProvider('unreadableReceipts')]
    public function testItReportsAnUnreadableReceiptWithoutRevealingValues(string $json, string $reason): void
    {
        try {
            (new PostingReceiptMapper())->fromPayload(JsonDecoder::decode($json));
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame($reason, $exception->reason());
            static::assertStringNotContainsString('SECRET', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadableReceipts(): iterable
    {
        $malformed = 'an "id" of the receipt is malformed or the receipt is empty';

        yield 'empty array' => ['[]', $malformed];
        yield 'not a uuid' => ['[{"id": "SECRET"}]', $malformed];
        yield 'one bad among good ones' => ['[{"id": "0b9f3f0e-0000-4000-8000-0000000000a1"}, {"id": "SECRET"}]', $malformed];
        yield 'missing id' => ['[{"other": "SECRET"}]', 'field "id" is missing or empty'];
        yield 'numeric id' => ['[{"id": 12}]', 'field "id" is missing or empty'];
        yield 'object' => ['{"id": "0b9f3f0e-0000-4000-8000-0000000000a1"}', 'expected a JSON array'];
        yield 'array of strings' => ['["SECRET"]', 'expected a JSON array of objects'];
    }
}

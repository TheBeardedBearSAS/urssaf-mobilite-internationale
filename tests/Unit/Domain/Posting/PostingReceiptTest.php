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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;

final class PostingReceiptTest extends TestCase
{
    public function testDossierId(): void
    {
        $id = DossierId::fromString('3FA85F64-5717-4562-B3FC-2C963F66AFA6');

        static::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', $id->value());
        static::assertSame('3fa85f64-5717-4562-b3fc-2c963f66afa6', (string) $id);
        static::assertTrue($id->equals(DossierId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa6')));
        static::assertFalse($id->equals(DossierId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa7')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'no dashes' => ['3fa85f6457174562b3fc2c963f66afa6'];
        yield 'too short' => ['3fa85f64-5717-4562-b3fc-2c963f66afa'];
        yield 'too long' => ['3fa85f64-5717-4562-b3fc-2c963f66afa66'];
        yield 'non hex' => ['3fa85f64-5717-4562-b3fc-2c963f66afag'];
        yield 'prefixed' => [' 3fa85f64-5717-4562-b3fc-2c963f66afa6'];
        yield 'trailing newline' => ["3fa85f64-5717-4562-b3fc-2c963f66afa6\n"];
    }

    #[DataProvider('provideInvalidIds')]
    public function testInvalidDossierId(string $value): void
    {
        InvalidValueAssertions::assertThrows(static fn(): DossierId => DossierId::fromString($value), 'dossier_id.invalid_format', ['expected_format' => 'UUID']);
    }

    public function testReceiptKeepsEveryIdInOrder(): void
    {
        $first = DossierId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa6');
        $second = DossierId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa7');

        $receipt = PostingReceipt::of($first, $second);

        static::assertSame([$first, $second], $receipt->dossierIds());
        static::assertSame($first, $receipt->first());
    }

    public function testStringKeyedSpreadStillYieldsAList(): void
    {
        $id = DossierId::fromString('3fa85f64-5717-4562-b3fc-2c963f66afa6');

        $receipt = PostingReceipt::of(...['some' => $id]);

        static::assertSame([$id], $receipt->dossierIds());
        static::assertSame($id, $receipt->first());
    }

    public function testEmptyReceiptIsRefused(): void
    {
        /** @var list<DossierId> $none */
        $none = [];

        InvalidValueAssertions::assertThrows(static fn(): PostingReceipt => PostingReceipt::of(...$none), 'posting_receipt.empty');
    }
}

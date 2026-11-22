<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;

final class DraftReaderTest extends TestCase
{
    public function testRequired(): void
    {
        $reader = new DraftReader();

        static::assertNull($reader->required('a', null, Label::fromString(...)));
        static::assertSame('', $reader->required('b', '', Label::fromString(...))?->value(), '"" is a value');
        static::assertNull($reader->required('c', 'x', Nir::fromString(...)));
        static::assertSame(['a field.required', 'c nir.invalid_format'], ViolationSummary::of($reader->result()));
        static::assertSame('This field is required.', $reader->result()->all()[0]->message());
    }

    public function testOptional(): void
    {
        $reader = new DraftReader();

        static::assertNull($reader->optional('a', null, Nir::fromString(...)));
        static::assertNull($reader->optional('b', '', Nir::fromString(...)), '"" is not absent');
        static::assertSame('x', $reader->optional('c', 'x', Label::fromString(...))?->value());
        static::assertSame(['b nir.invalid_format'], ViolationSummary::of($reader->result()));
    }

    public function testFlag(): void
    {
        $reader = new DraftReader();

        static::assertNull($reader->flag('a', null));
        static::assertFalse($reader->flag('b', false));
        static::assertTrue($reader->flag('c', true));
        static::assertSame(['a field.required'], ViolationSummary::of($reader->result()));
    }

    public function testAttempt(): void
    {
        $reader = new DraftReader();

        static::assertNull($reader->attempt('a.b', static fn(): never => throw InvalidValue::because('x.y', 'Broken.')));
        static::assertSame(1, $reader->attempt('c', static fn(): int => 1));
        static::assertSame(['a.b x.y'], ViolationSummary::of($reader->result()));
        static::assertTrue((new DraftReader())->result()->isValid());
    }
}

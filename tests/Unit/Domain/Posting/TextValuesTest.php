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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostalCode;

final class TextValuesTest extends TestCase
{
    public function testLabel(): void
    {
        $label = Label::fromString('Nom exemple');

        static::assertSame('Nom exemple', $label->value());
        static::assertTrue($label->equals(Label::fromString('Nom exemple')));
        static::assertFalse($label->equals(Label::fromString('nom exemple')));
        static::assertSame('', Label::fromString('')->value(), 'no minimum length');
        static::assertSame(255, mb_strlen(Label::fromString(str_repeat('é', 255))->value()), 'characters, not bytes');
    }

    public function testLabelTooLong(): void
    {
        $input = str_repeat('é', 256);

        InvalidValueAssertions::assertThrows(static fn(): Label => Label::fromString($input), 'label.too_long', ['max_length' => 255], 'éé');
    }

    public function testPostalCode(): void
    {
        $code = PostalCode::fromString('XX-0000 ZZ');

        static::assertSame('XX-0000 ZZ', $code->value());
        static::assertTrue($code->equals(PostalCode::fromString('XX-0000 ZZ')));
        static::assertFalse($code->equals(PostalCode::fromString('XX0000ZZ')));
        static::assertSame(15, mb_strlen(PostalCode::fromString(str_repeat('é', 15))->value()));
    }

    public function testPostalCodeTooLong(): void
    {
        InvalidValueAssertions::assertThrows(
            static fn(): PostalCode => PostalCode::fromString('1234567890123456'),
            'postal_code.too_long',
            ['max_length' => 15],
            '1234567890123456',
        );
    }

    public function testFreeText(): void
    {
        $text = FreeText::fromString(str_repeat('é', 10_000));

        static::assertSame(10_000, mb_strlen($text->value()), 'no maximum length');
        static::assertTrue(FreeText::fromString('')->equals(FreeText::fromString('')));
        static::assertFalse(FreeText::fromString('a')->equals(FreeText::fromString('A')));
        static::assertSame(['value' => '***'], FreeText::fromString('Commentaire')->__debugInfo());
    }
}

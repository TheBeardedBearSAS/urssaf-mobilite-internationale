<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Enrolment;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

final class EnrolmentPageTest extends TestCase
{
    public function testFullPage(): void
    {
        $first = self::enrolment('3fa85f64-5717-4562-b3fc-2c963f66afa6');
        $second = self::enrolment('3fa85f64-5717-4562-b3fc-2c963f66afa7');

        $page = EnrolmentPage::of([$first, $second], 20, 2);

        static::assertSame([$first, $second], $page->enrolments());
        static::assertSame(20, $page->offset());
        static::assertSame(2, $page->limit());
        static::assertFalse($page->isLastPage());
        static::assertSame(22, $page->nextOffset());
    }

    public function testShortPageIsTheLastOne(): void
    {
        $page = EnrolmentPage::of([self::enrolment('3fa85f64-5717-4562-b3fc-2c963f66afa6')], 0, 2);

        static::assertTrue($page->isLastPage());
        static::assertNull($page->nextOffset());
        static::assertTrue(EnrolmentPage::of([], 0, 1)->isLastPage());
        static::assertSame([], EnrolmentPage::of([], 5, 1)->enrolments());
    }

    public function testBoundaries(): void
    {
        static::assertSame(0, EnrolmentPage::of([], 0, 1)->offset());
        static::assertSame(1, EnrolmentPage::of([], 0, 1)->limit());

        try {
            EnrolmentPage::of([], -1, 1);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('offset.negative', $invalidValue->violationCode());
        }

        try {
            EnrolmentPage::of([], 0, 0);
            static::fail('InvalidValue expected.');
        } catch (InvalidValue $invalidValue) {
            static::assertSame('limit.not_positive', $invalidValue->violationCode());
        }
    }

    private static function enrolment(string $id): Enrolment
    {
        return Enrolment::of(EnrolmentId::fromString($id), null, null, null, null, null, null);
    }
}

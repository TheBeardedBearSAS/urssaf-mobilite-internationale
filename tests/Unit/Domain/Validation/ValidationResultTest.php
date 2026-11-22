<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

final class ValidationResultTest extends TestCase
{
    public function testResultCollectsInOrder(): void
    {
        $first = Violation::error('a', 'a.one', 'One.');
        $second = Violation::error('b', 'b.two', 'Two.');
        $third = Violation::error('c', 'c.three', 'Three.');

        static::assertTrue(ValidationResult::valid()->isValid());
        static::assertSame([], ValidationResult::valid()->all());
        static::assertFalse(ValidationResult::of($first)->isValid());
        static::assertSame([$first, $second], ValidationResult::of($first, $second)->all());
        static::assertSame([$first, $second], ValidationResult::of($first)->with($second)->all());
        static::assertSame([$first, $second], ValidationResult::of(...['a' => $first, 'b' => $second])->all(), 'always a list');
        static::assertSame(
            [$first, $second, $third],
            ValidationResult::of($first)->merge(ValidationResult::of($second, $third))->all(),
        );
        static::assertSame([$first, $second, $third], ValidationResult::of($first, $second)->with($third)->all());
        static::assertSame(
            [$first, $second, $third, $first],
            ValidationResult::of($first, $second)->merge(ValidationResult::of($third, $first))->all(),
        );
        static::assertSame([$first], ValidationResult::valid()->with($first)->all());
        static::assertSame([$first], ValidationResult::valid()->merge(ValidationResult::of($first))->all());
    }

    public function testResultWithPathPrefix(): void
    {
        $result = ValidationResult::of(Violation::error('country', 'a.b', 'M.'), Violation::error('', 'c.d', 'N.'));

        $paths = array_map(static fn(Violation $violation): string => $violation->path(), $result->withPathPrefix('activities[0]')->all());

        static::assertSame(['activities[0].country', 'activities[0]'], $paths);
    }

    public function testThrowIfInvalid(): void
    {
        ValidationResult::valid()->throwIfInvalid();
        $first = Violation::error('a', 'a.one', 'One.');
        $result = ValidationResult::of($first, Violation::error('b', 'b.two', 'Two.'));

        try {
            $result->throwIfInvalid();
            static::fail('ValidationFailed expected.');
        } catch (ValidationFailed $failed) {
            static::assertSame($result, $failed->result());
            static::assertSame('validation.failed', $failed->violationCode());
            static::assertSame('Validation failed with 2 error(s).', $failed->getMessage());
            static::assertSame(['error_count' => 2], $failed->params());
        }
    }
}

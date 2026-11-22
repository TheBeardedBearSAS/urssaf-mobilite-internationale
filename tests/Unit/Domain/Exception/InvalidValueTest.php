<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Exception;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvariantViolation;

final class InvalidValueTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): InvalidValue, string, string, array<string, scalar>}>
     */
    public static function provideNamedConstructors(): iterable
    {
        yield 'because' => [static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom.', ['limit' => 3]), 'foo.bar', 'Custom.', ['limit' => 3]];
        yield 'because without params' => [static fn(): InvalidValue => InvalidValue::because('foo.bar', 'Custom.'), 'foo.bar', 'Custom.', []];
        yield 'empty' => [static fn(): InvalidValue => InvalidValue::empty('tenant_id'), 'tenant_id.empty', 'Invalid tenant_id: the value must not be empty.', []];
        yield 'too long' => [
            static fn(): InvalidValue => InvalidValue::tooLong('tenant_id', 64),
            'tenant_id.too_long',
            'Invalid tenant_id: the value must not exceed 64 characters.',
            ['max_length' => 64],
        ];
        yield 'invalid format' => [
            static fn(): InvalidValue => InvalidValue::invalidFormat('siren', 'digits'),
            'siren.invalid_format',
            'Invalid siren: the value does not match the expected format (digits).',
            ['expected_format' => 'digits'],
        ];
        yield 'exact length' => [
            static fn(): InvalidValue => InvalidValue::invalidLength('siren', 9, 9),
            'siren.invalid_length',
            'Invalid siren: the value must be exactly 9 characters long.',
            ['min_length' => 9, 'max_length' => 9],
        ];
        yield 'length range' => [
            static fn(): InvalidValue => InvalidValue::invalidLength('phone_number', 7, 30),
            'phone_number.invalid_length',
            'Invalid phone_number: the value must be between 7 and 30 characters long.',
            ['min_length' => 7, 'max_length' => 30],
        ];
        yield 'negative' => [static fn(): InvalidValue => InvalidValue::negative('offset'), 'offset.negative', 'Invalid offset: the value must not be negative.', []];
        yield 'not positive' => [
            static fn(): InvalidValue => InvalidValue::notPositive('limit'),
            'limit.not_positive',
            'Invalid limit: the value must be strictly positive.',
            [],
        ];
    }

    /**
     * @param Closure(): InvalidValue $factory
     * @param array<string, scalar>   $params
     */
    #[DataProvider('provideNamedConstructors')]
    public function testNamedConstructors(Closure $factory, string $code, string $message, array $params): void
    {
        $exception = $factory();

        static::assertSame($code, $exception->violationCode());
        static::assertSame($message, $exception->getMessage());
        static::assertSame($params, $exception->params());
    }

    public function testToViolation(): void
    {
        $violation = InvalidValue::tooLong('tenant_id', 64)->toViolation('tenant.id');

        static::assertSame('tenant.id', $violation->path());
        static::assertSame('tenant_id.too_long', $violation->code());
        static::assertSame('Invalid tenant_id: the value must not exceed 64 characters.', $violation->message());
        static::assertSame(['max_length' => 64], $violation->params());
    }

    public function testInvariantViolation(): void
    {
        $exception = InvariantViolation::because('a.b', 'Broken.', ['x' => 1]);

        static::assertSame('a.b', $exception->violationCode());
        static::assertSame('Broken.', $exception->getMessage());
        static::assertSame(['x' => 1], $exception->params());
        static::assertSame([], InvariantViolation::because('a.b', 'Broken.')->params());
    }
}

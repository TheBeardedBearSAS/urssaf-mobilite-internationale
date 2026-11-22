<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception;

/**
 * Thrown when a value object cannot be built from the given input.
 *
 * The "subject" is the snake_case name of the value (e.g. "tenant_id", "siren"); the violation code is
 * "<subject>.<reason>" (e.g. "tenant_id.too_long"). The rejected value is never part of the message nor of the parameters.
 */
final class InvalidValue extends DomainException
{
    /**
     * @param array<string, scalar> $params
     */
    public static function because(string $violationCode, string $message, array $params = []): self
    {
        return new self($message, $violationCode, $params);
    }

    public static function empty(string $subject): self
    {
        return new self(\sprintf('Invalid %s: the value must not be empty.', $subject), $subject . '.empty', []);
    }

    public static function tooLong(string $subject, int $maxLength): self
    {
        return new self(
            \sprintf('Invalid %s: the value must not exceed %d characters.', $subject, $maxLength),
            $subject . '.too_long',
            ['max_length' => $maxLength],
        );
    }

    public static function invalidFormat(string $subject, string $expectedFormat): self
    {
        return new self(
            \sprintf('Invalid %s: the value does not match the expected format (%s).', $subject, $expectedFormat),
            $subject . '.invalid_format',
            ['expected_format' => $expectedFormat],
        );
    }

    public static function invalidLength(string $subject, int $minLength, int $maxLength): self
    {
        return new self(
            $minLength === $maxLength
                ? \sprintf('Invalid %s: the value must be exactly %d characters long.', $subject, $minLength)
                : \sprintf('Invalid %s: the value must be between %d and %d characters long.', $subject, $minLength, $maxLength),
            $subject . '.invalid_length',
            ['min_length' => $minLength, 'max_length' => $maxLength],
        );
    }

    public static function negative(string $subject): self
    {
        return new self(\sprintf('Invalid %s: the value must not be negative.', $subject), $subject . '.negative', []);
    }

    public static function notPositive(string $subject): self
    {
        return new self(
            \sprintf('Invalid %s: the value must be strictly positive.', $subject),
            $subject . '.not_positive',
            [],
        );
    }
}

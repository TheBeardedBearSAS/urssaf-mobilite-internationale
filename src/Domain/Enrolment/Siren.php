<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment;

use Stringable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * SIREN of an enrolment: exactly 9 characters ("minLength" = "maxLength" = 9), no pattern, no checksum (ADR 0008).
 * A public registry identifier, not masked in debug output (ADR 0006). Kept verbatim.
 */
final readonly class Siren implements Stringable
{
    public const LENGTH = 9;
    private const SUBJECT = 'siren';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "siren.invalid_length"
     */
    public static function fromString(string $value): self
    {
        if (mb_strlen($value) !== self::LENGTH) {
            throw InvalidValue::invalidLength(self::SUBJECT, self::LENGTH, self::LENGTH);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

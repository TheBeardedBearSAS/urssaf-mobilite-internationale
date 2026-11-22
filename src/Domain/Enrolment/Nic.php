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
 * NIC of an enrolment: exactly 5 characters ("minLength" = "maxLength" = 5), no pattern (ADR 0008). Kept verbatim.
 */
final readonly class Nic implements Stringable
{
    public const LENGTH = 5;
    private const SUBJECT = 'nic';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nic.invalid_length"
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

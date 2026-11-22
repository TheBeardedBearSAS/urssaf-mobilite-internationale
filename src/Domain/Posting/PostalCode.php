<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Postal code of an address ("codePostal"): at most 15 characters, foreign codes included. No format per country
 * (ADR 0008). Kept verbatim. Personal data.
 */
final readonly class PostalCode
{
    use PersonalData;

    public const MAX_LENGTH = 15;
    private const SUBJECT = 'postal_code';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "postal_code.too_long"
     */
    public static function fromString(string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
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
}

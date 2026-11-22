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
 * Name, company name or address line of the posting request: at most 255 characters ("maxLength" of the contract),
 * counted in characters. No minimum, no pattern (ADR 0008): "" is accepted. Kept verbatim. Personal data.
 */
final readonly class Label
{
    use PersonalData;

    public const MAX_LENGTH = 255;
    private const SUBJECT = 'label';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "label.too_long"
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

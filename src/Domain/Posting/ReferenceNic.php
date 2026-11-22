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
 * NIC of the "referenceEntreprise": at most 5 digits, pattern "[0-9]*" and **no minimum** (api-reference §5.14), so ""
 * is accepted. Differs from the enrolment NIC ({@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic}).
 * A public registry identifier, not masked (ADR 0006). Kept verbatim.
 */
final readonly class ReferenceNic
{
    public const MAX_LENGTH = 5;
    public const FORMAT = '[0-9]*';
    private const SUBJECT = 'nic';
    private const PATTERN = '/^[0-9]*$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nic.too_long" or "nic.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, self::FORMAT);
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

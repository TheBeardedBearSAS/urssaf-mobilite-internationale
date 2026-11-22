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
 * SIREN of the "referenceEntreprise": pattern "[0-9A-Z]{9}", stricter than the enrolment SIREN
 * ({@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren}). No checksum (ADR 0008). A public registry
 * identifier, not masked (ADR 0006). Kept verbatim.
 */
final readonly class ReferenceSiren
{
    public const FORMAT = '[0-9A-Z]{9}';
    private const SUBJECT = 'siren';
    private const PATTERN = '/^[0-9A-Z]{9}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "siren.invalid_format"
     */
    public static function fromString(string $value): self
    {
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

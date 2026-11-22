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
 * NIR of the posted person, without its key ("nir"): 13 characters, pattern "[0-9]{6}[0-9AaBb][0-9]{6}" (the 7th
 * character may be the Corsican "A" / "B"). No key check against {@see NirKey} (ADR 0008). Kept verbatim. Personal data.
 */
final readonly class Nir
{
    use PersonalData;

    public const FORMAT = '[0-9]{6}[0-9AaBb][0-9]{6}';
    private const SUBJECT = 'nir';
    private const PATTERN = '/^[0-9]{6}[0-9AaBb][0-9]{6}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nir.invalid_format"
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

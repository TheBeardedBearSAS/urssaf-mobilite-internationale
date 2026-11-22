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
 * Key of the NIR ("cleNir"): exactly 2 digits. Not checked against the {@see Nir} (ADR 0008). Personal data.
 */
final readonly class NirKey
{
    use PersonalData;

    public const FORMAT = '[0-9]{2}';
    private const SUBJECT = 'nir_key';
    private const PATTERN = '/^[0-9]{2}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nir_key.invalid_format"
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

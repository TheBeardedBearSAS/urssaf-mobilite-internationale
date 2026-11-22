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
 * External account number of the "referenceEntreprise" ("numeroCompteExterne"): exactly 18 digits. Kept verbatim.
 */
final readonly class ExternalAccountNumber
{
    public const FORMAT = '[0-9]{18}';
    private const SUBJECT = 'external_account_number';
    private const PATTERN = '/^[0-9]{18}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "external_account_number.invalid_format"
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

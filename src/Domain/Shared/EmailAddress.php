<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * E-mail address ("format: email" of the contract). Minimal shape only: exactly one "@", a non-empty local part and a
 * non-empty domain, no whitespace. No RFC grammar: the server is the judge (ADR 0008). Kept verbatim.
 */
final readonly class EmailAddress
{
    private const SUBJECT = 'email_address';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        $parts = explode('@', $value);
        if (\count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || preg_match('/\s/', $value) === 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'local@domain');
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

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}

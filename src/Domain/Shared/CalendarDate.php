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

use Stringable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Date without time nor time zone ("format: date" of the contract), "Y-m-d".
 */
final readonly class CalendarDate implements Stringable
{
    private const SUBJECT = 'calendar_date';
    private const PATTERN = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "calendar_date.invalid_format" or "calendar_date.invalid_date" (e.g. 2023-02-29)
     */
    public static function fromString(string $date): self
    {
        $parts = [];
        if (preg_match(self::PATTERN, $date, $parts) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'Y-m-d');
        }
        if (!checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])) {
            throw InvalidValue::because(self::SUBJECT . '.invalid_date', 'Invalid calendar_date: the date does not exist.');
        }

        return new self($date);
    }

    /**
     * "Y-m-d".
     */
    public function toString(): string
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

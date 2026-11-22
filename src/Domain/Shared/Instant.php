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

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Point on the time line ("format: date-time" of the contract), normalised to UTC with second precision.
 */
final readonly class Instant implements Stringable
{
    private const SUBJECT = 'instant';
    /** Local part, optional fraction, then "Z" or an offset with or without colon ("+01:00", "+0100"). */
    private const ISO_PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/D';

    private DateTimeImmutable $utc;

    private function __construct(DateTimeInterface $dateTime)
    {
        $this->utc = (new DateTimeImmutable('@' . $dateTime->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self($dateTime);
    }

    /**
     * ISO 8601 with an explicit offset: "2022-02-01T16:40:00Z", "2022-02-01T16:40:00+01:00", "2022-02-01T16:40:00.123+0100".
     * Fractional seconds are accepted and truncated.
     *
     * @throws InvalidValue "instant.invalid_format" or "instant.invalid_date"
     */
    public static function fromIso8601(string $value): self
    {
        $matches = [];
        if (preg_match(self::ISO_PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'ISO 8601 date-time with offset');
        }
        $parsed = date_create_immutable($value);
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s') !== $matches[1]) {
            throw InvalidValue::because(self::SUBJECT . '.invalid_date', 'Invalid instant: the date does not exist.');
        }

        return new self($parsed);
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return $this->utc;
    }

    /**
     * "2022-02-01T16:40:00Z".
     */
    public function toIso8601(): string
    {
        return $this->utc->format('Y-m-d\TH:i:s\Z');
    }

    public function equals(self $other): bool
    {
        return $this->utc->getTimestamp() === $other->utc->getTimestamp();
    }

    public function __toString(): string
    {
        return $this->toIso8601();
    }
}

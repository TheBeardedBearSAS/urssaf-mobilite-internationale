<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

/**
 * Dates of the contract: `format: date` is `yyyy-MM-dd`; `format: date-time` is ISO 8601 with an offset whose notation
 * is not published (api-reference §3.8), so `Z`, `+01:00` and `+0100` are all read.
 */
final class DateEncoding
{
    public static function encodeCalendarDate(CalendarDate $date): string
    {
        return $date->toString();
    }

    /**
     * @throws UnreadablePayload when the value is not an ISO 8601 date-time with an offset
     */
    public static function decodeInstant(string $value): Instant
    {
        try {
            return Instant::fromIso8601($value);
        } catch (InvalidValue $exception) {
            throw UnreadablePayload::causedBy('a date-time is not valid ISO 8601 with an offset', $exception);
        }
    }
}

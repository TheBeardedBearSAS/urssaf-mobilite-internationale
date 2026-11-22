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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;

/**
 * Helpers shared by the request encoders.
 *
 * @internal
 */
final class WireMembers
{
    /**
     * Drops the members that are not set: an optional value is omitted, never sent as null.
     *
     * @param array<string, mixed> $members
     *
     * @return array<string, mixed>
     */
    public static function present(array $members): array
    {
        return array_filter($members, static fn(mixed $member): bool => $member !== null);
    }

    public static function date(?CalendarDate $date): ?string
    {
        return $date instanceof CalendarDate ? DateEncoding::encodeCalendarDate($date) : null;
    }
}

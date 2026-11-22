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
 * Activity type ("type" of an activity): the contract lists the single value "AUTRE".
 */
enum ActivityType: string
{
    case Autre = 'AUTRE';

    /**
     * @throws InvalidValue "activity_type.unknown" for any value not in the contract (the value is never repeated)
     */
    public static function fromWire(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidValue::because('activity_type.unknown', 'Invalid activity type: the value is not in the list of the contract.');
    }
}

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
 * Activity criterion ("CritereDActivite" schema, api-reference §3.5). Backed by the wire values, verbatim.
 */
enum ActivityCriterion: string
{
    case CA = 'CA';
    case TempsTravail = 'TempsTravail';
    case Prestations = 'Prestations';
    case Remuneration = 'Remuneration';

    /**
     * @throws InvalidValue "activity_criterion.unknown" for any value not in the contract (the value is never repeated)
     */
    public static function fromWire(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidValue::because('activity_criterion.unknown', 'Invalid activity criterion: the value is not in the list of the contract.');
    }
}

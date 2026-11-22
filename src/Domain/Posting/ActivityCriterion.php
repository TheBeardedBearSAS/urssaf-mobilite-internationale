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

/**
 * Activity criterion ("CritereDActivite" schema, api-reference §3.5). Backed by the wire values, verbatim.
 */
enum ActivityCriterion: string
{
    case CA = 'CA';
    case TempsTravail = 'TempsTravail';
    case Prestations = 'Prestations';
    case Remuneration = 'Remuneration';
}

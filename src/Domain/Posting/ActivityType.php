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
 * Activity type ("type" of an activity): the contract lists the single value "AUTRE".
 */
enum ActivityType: string
{
    case Autre = 'AUTRE';
}

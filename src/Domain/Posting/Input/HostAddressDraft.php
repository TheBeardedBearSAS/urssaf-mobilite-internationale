<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PersonalData;

/**
 * Raw "adresseAccueil": "usualAddress" = "adresseHabituelle" (the address is not read when true).
 */
final readonly class HostAddressDraft
{
    use PersonalData;

    public function __construct(
        public ?bool $usualAddress,
        public ?AddressDraft $address,
    ) {}
}

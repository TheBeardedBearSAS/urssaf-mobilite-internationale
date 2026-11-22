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
 * Raw address ("Adresse"): "line" = "adresse", "complement" = "adresseCplt", "city" = "ville",
 * "postalCode" = "codePostal", "country" = "pays" (wire value of the contract, e.g. "ITALIE").
 */
final readonly class AddressDraft
{
    use PersonalData;

    public function __construct(
        public ?string $line,
        public ?string $complement,
        public ?string $city,
        public ?string $postalCode,
        public ?string $country,
    ) {}
}

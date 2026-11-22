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
 * Raw "Entreprise": "name" = "raisonSociale", "addressKnown" = "adresseConnue" (the address is not read when false).
 */
final readonly class CompanyDraft
{
    use PersonalData;

    public function __construct(
        public ?string $name,
        public ?bool $addressKnown,
        public ?AddressDraft $address,
        public ?string $email,
    ) {}
}

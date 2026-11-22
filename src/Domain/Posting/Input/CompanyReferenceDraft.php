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

/**
 * Raw "referenceEntreprise": "externalAccountNumber" = "numeroCompteExterne".
 */
final readonly class CompanyReferenceDraft
{
    public function __construct(
        public ?string $siren,
        public ?string $nic,
        public ?string $externalAccountNumber,
    ) {}
}

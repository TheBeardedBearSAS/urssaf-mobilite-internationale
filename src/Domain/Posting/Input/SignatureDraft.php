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
 * Raw "signature": "personalDataAuthorisation" = "autorisationDonneesPerso", "declarationOnHonour" = "declarationHonneur".
 */
final readonly class SignatureDraft
{
    public function __construct(
        public ?bool $personalDataAuthorisation,
        public ?bool $declarationOnHonour,
    ) {}
}

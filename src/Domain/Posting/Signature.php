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
 * Legal statements ("signature"): both booleans required. The contract does not require them to be true.
 */
final readonly class Signature
{
    private function __construct(private bool $personalDataAuthorisation, private bool $declarationOnHonour) {}

    /**
     * @param bool $personalDataAuthorisation "autorisationDonneesPerso"
     * @param bool $declarationOnHonour       "declarationHonneur"
     */
    public static function of(bool $personalDataAuthorisation, bool $declarationOnHonour): self
    {
        return new self($personalDataAuthorisation, $declarationOnHonour);
    }

    public function personalDataAuthorisation(): bool
    {
        return $this->personalDataAuthorisation;
    }

    public function declarationOnHonour(): bool
    {
        return $this->declarationOnHonour;
    }
}

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
 * Administrative identification of the declaring company ("referenceEntreprise"): "siren" and "nic" required,
 * "numeroCompteExterne" optional. The posting requires a validated enrolment of this company (403 otherwise).
 */
final readonly class CompanyReference
{
    private function __construct(
        private ReferenceSiren $siren,
        private ReferenceNic $nic,
        private ?ExternalAccountNumber $externalAccountNumber,
    ) {}

    public static function of(ReferenceSiren $siren, ReferenceNic $nic, ?ExternalAccountNumber $externalAccountNumber): self
    {
        return new self($siren, $nic, $externalAccountNumber);
    }

    public function siren(): ReferenceSiren
    {
        return $this->siren;
    }

    public function nic(): ReferenceNic
    {
        return $this->nic;
    }

    public function externalAccountNumber(): ?ExternalAccountNumber
    {
        return $this->externalAccountNumber;
    }
}

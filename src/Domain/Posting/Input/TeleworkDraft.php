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
 * Raw "teletravail": "teleworking" = "teletravail", "workerAgreement" = "accordSalarie", "country" = "pays", "daysPerMonth" = "nombreJours".
 */
final readonly class TeleworkDraft
{
    use PersonalData;

    public function __construct(
        public ?bool $teleworking,
        public ?bool $workerAgreement,
        public ?string $country,
        public ?int $daysPerMonth,
    ) {}
}

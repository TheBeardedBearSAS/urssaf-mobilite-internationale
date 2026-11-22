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
 * Raw "activiteDeclareeCplt", same fields and order as {@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails::of()}.
 */
final readonly class ActivityDetailsDraft
{
    public function __construct(
        public ?bool $replacesAnotherEmployee,
        public ?bool $affiliatedToFrenchSocialSecurity,
        public ?bool $remunerationModifiable,
        public ?bool $dismissalByHostCompany,
        public ?bool $disciplinarySanctionsByHostCompany,
        public ?bool $foreignEmploymentContract,
    ) {}
}

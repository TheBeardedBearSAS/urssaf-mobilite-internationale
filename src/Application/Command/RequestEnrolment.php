<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Command;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Requests the enrolment of a company ("POST /enrolements"). The enrolment then waits for a human validation on
 * urssaf.fr: poll it with {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment}.
 */
final readonly class RequestEnrolment
{
    public function __construct(
        public TenantId $tenantId,
        public Siren $siren,
        public ?Nic $nic,
        public EnrolmentCategory $category,
    ) {}
}

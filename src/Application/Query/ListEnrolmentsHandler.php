<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Query;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;

final readonly class ListEnrolmentsHandler
{
    public function __construct(private MobilityGateway $gateway) {}

    public function __invoke(ListEnrolments $query): EnrolmentPage
    {
        return $this->gateway->listEnrolments($query->tenantId, $query->offset, $query->limit, $query->sort);
    }
}

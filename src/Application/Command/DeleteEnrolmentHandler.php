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

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;

/**
 * After an {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable} that may have
 * reached URSSAF, confirm with {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment}
 * (404 or SUPPRIME): the answer of a second delete is not published (ADR 0004).
 */
final readonly class DeleteEnrolmentHandler
{
    public function __construct(private MobilityGateway $gateway) {}

    public function __invoke(DeleteEnrolment $command): void
    {
        $this->gateway->deleteEnrolment($command->tenantId, $command->enrolmentId);
    }
}

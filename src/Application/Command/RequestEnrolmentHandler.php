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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;

/**
 * Never retried: an uncertain outcome surfaces as {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown}
 * (ADR 0004); the caller lists the enrolments and looks for the SIREN before resending.
 */
final readonly class RequestEnrolmentHandler
{
    public function __construct(private MobilityGateway $gateway) {}

    public function __invoke(RequestEnrolment $command): Enrolment
    {
        return $this->gateway->requestEnrolment($command->tenantId, $command->siren, $command->nic, $command->category);
    }
}

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

use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\EnrolmentNotAccepted;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;

/**
 * Order: validation policy (ADR 0008), optional enrolment guard (ADR 0009), then one single call. Never retried nor
 * resent: an uncertain outcome surfaces as {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown}
 * (ADR 0004). The caller must store the receipt before anything else: nothing lists the postings.
 */
final readonly class SubmitPostingHandler
{
    /**
     * @param PostingValidationPolicy $policy {@see PostingValidationPolicy::default()} for the Swagger rules only
     */
    public function __construct(private MobilityGateway $gateway, private PostingValidationPolicy $policy) {}

    /**
     * @throws ValidationFailed     when the policy refuses the request (nothing sent)
     * @throws EnrolmentNotAccepted when the optional guard refuses the enrolment (nothing submitted)
     */
    public function __invoke(SubmitPosting $command): PostingReceipt
    {
        $this->policy->validate($command->request)->throwIfInvalid();
        if ($command->enrolmentToCheck instanceof EnrolmentId) {
            $this->assertAccepted($command, $command->enrolmentToCheck);
        }

        return $this->gateway->submitPosting($command->tenantId, $command->request);
    }

    private function assertAccepted(SubmitPosting $command, EnrolmentId $enrolmentId): void
    {
        $enrolment = $this->gateway->getEnrolment($command->tenantId, $enrolmentId);
        if (!$enrolment->isAccepted()) {
            throw EnrolmentNotAccepted::notAccepted($enrolmentId);
        }
        $siren = $enrolment->siren();
        if (!$siren instanceof Siren || $siren->value() !== $command->request->declaration()->companyReference()->siren()->value()) {
            throw EnrolmentNotAccepted::otherCompany($enrolmentId);
        }
    }
}

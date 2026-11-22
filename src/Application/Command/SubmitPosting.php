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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Submits a posting request ("POST /declaration/detachement/UE"). Holds personal data (ADR 0006): never logged nor
 * serialized by the package.
 *
 * {@see self::of()} does not check the enrolment (URSSAF answers 403 when none is accepted); the optional guard of
 * {@see self::afterCheckingEnrolment()} reads the enrolment first (ADR 0009).
 */
final readonly class SubmitPosting
{
    private function __construct(
        public TenantId $tenantId,
        public PostingRequest $request,
        public ?EnrolmentId $enrolmentToCheck,
    ) {}

    public static function of(TenantId $tenantId, PostingRequest $request): self
    {
        return new self($tenantId, $request, null);
    }

    /**
     * The handler reads the enrolment and throws {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\EnrolmentNotAccepted}
     * unless its status is ACCEPTE and its SIREN is the one of the "referenceEntreprise"; nothing is submitted then.
     */
    public static function afterCheckingEnrolment(TenantId $tenantId, PostingRequest $request, EnrolmentId $enrolmentId): self
    {
        return new self($tenantId, $request, $enrolmentId);
    }
}

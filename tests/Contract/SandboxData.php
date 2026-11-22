<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyReferenceDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingDetailsDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\SignatureDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input\PostingDrafts;

/**
 * Sandbox data read from the environment. The repository holds no real identifier: every SIREN, NIC, dossier id comes
 * from the variables below, and the people, the contact details and the addresses of a posting are the synthetic
 * values of the unit tests.
 *
 * - URSSAF_SANDBOX_ENROLMENT_SIREN (required to run the enrolment life-cycle), URSSAF_SANDBOX_ENROLMENT_NIC (optional)
 *   and URSSAF_SANDBOX_ENROLMENT_CATEGORY (optional, default RG);
 * - URSSAF_SANDBOX_POSTING_SIREN and URSSAF_SANDBOX_POSTING_NIC (both required to submit a posting: a company whose
 *   enrolment was validated) and URSSAF_SANDBOX_POSTING_EXTERNAL_ACCOUNT (optional, 18 digits);
 * - URSSAF_SANDBOX_DOSSIER_ID (a dossier number, for the certificate test).
 */
final class SandboxData
{
    private const DEFAULT_CATEGORY = 'RG';

    public static function enrolmentSiren(): ?Siren
    {
        $siren = SandboxGateway::env('URSSAF_SANDBOX_ENROLMENT_SIREN');

        return $siren === null ? null : Siren::fromString($siren);
    }

    public static function enrolmentNic(): ?Nic
    {
        $nic = SandboxGateway::env('URSSAF_SANDBOX_ENROLMENT_NIC');

        return $nic === null ? null : Nic::fromString($nic);
    }

    public static function enrolmentCategory(): EnrolmentCategory
    {
        return EnrolmentCategory::from(SandboxGateway::env('URSSAF_SANDBOX_ENROLMENT_CATEGORY') ?? self::DEFAULT_CATEGORY);
    }

    public static function dossierId(): ?string
    {
        return SandboxGateway::env('URSSAF_SANDBOX_DOSSIER_ID');
    }

    /**
     * Null while the sandbox data of the posting are not provided: nothing is sent then.
     */
    public static function posting(): ?PostingRequest
    {
        $siren = SandboxGateway::env('URSSAF_SANDBOX_POSTING_SIREN');
        $nic = SandboxGateway::env('URSSAF_SANDBOX_POSTING_NIC');
        if ($siren === null || $nic === null) {
            return null;
        }

        $details = PostingDrafts::details();
        $start = new DateTimeImmutable('+30 days', new DateTimeZone('UTC'));
        $dated = new PostingDetailsDraft(
            $details->worker,
            $start->format('Y-m-d'),
            $start->modify('+90 days')->format('Y-m-d'),
            $details->telework,
            $details->hostAddress,
            $details->sameActivitySector,
            $details->durableInfrastructure,
            $details->substantialActivity,
            $details->declaredActivity,
            $details->currentActivities,
            $details->comment,
        );
        $declaration = new DeclarationDraft(
            PostingDrafts::company(),
            new CompanyReferenceDraft($siren, $nic, SandboxGateway::env('URSSAF_SANDBOX_POSTING_EXTERNAL_ACCOUNT')),
            new SignatureDraft(true, true),
            $dated,
        );

        return PostingRequest::fromDraft(
            new PostingRequestDraft(PostingDrafts::declarant(), $declaration),
            PostingValidationPolicy::default(),
        );
    }
}

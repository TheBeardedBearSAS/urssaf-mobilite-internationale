<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * The six operations of the URSSAF "API Mobilité internationale", implemented by the Infrastructure layer. The
 * tenant selects the OAuth2 credentials (ADR 0007). The gateway never retries nor resends; it classifies (ADR 0002,
 * ADR 0004):
 * - {@see UrssafRequestRejected}: HTTP 400 (and 403 on {@see self::submitPosting()}), nothing recorded;
 * - {@see UrssafAuthenticationFailed}: credentials refused, 401 after the single replay, 403 elsewhere;
 * - {@see UrssafResourceNotFound}: HTTP 404 on the enrolment operations;
 * - {@see UrssafUnavailable} ({@see RetryableException}): 429, transport errors and 5xx on the reads and the deletion;
 * - {@see OutcomeUnknown}: uncertain outcome of the two POST operations, never resent;
 * - any other {@see UrssafException}: unexpected answer of the API (not processed).
 */
interface MobilityGateway
{
    /**
     * POST /enrolements (201).
     *
     * @throws UrssafException
     */
    public function requestEnrolment(TenantId $tenantId, Siren $siren, ?Nic $nic, EnrolmentCategory $category): Enrolment;

    /**
     * GET /enrolements with flat query parameters; the caller chooses its page size (ADR 0009).
     *
     * @param int         $offset ≥ 0
     * @param int         $limit  ≥ 1, no maximum enforced
     * @param string|null $sort   sent verbatim when not null (syntax not published)
     *
     * @throws UrssafException
     */
    public function listEnrolments(TenantId $tenantId, int $offset, int $limit, ?string $sort): EnrolmentPage;

    /**
     * GET /enrolements/{id}.
     *
     * @throws UrssafException
     */
    public function getEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): Enrolment;

    /**
     * DELETE /enrolements/{id} (200, body ignored). After an {@see UrssafUnavailable} that may have reached URSSAF,
     * confirm with {@see self::getEnrolment()} (404 or SUPPRIME).
     *
     * @throws UrssafException
     */
    public function deleteEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): void;

    /**
     * POST /declaration/detachement/UE (200). Store the receipt before anything else: nothing lists the postings.
     *
     * @throws UrssafException
     */
    public function submitPosting(TenantId $tenantId, PostingRequest $request): PostingReceipt;

    /**
     * GET /certificat/{id}: "not ready yet" and "refused" are results, not exceptions (ADR 0003).
     *
     * @throws UrssafException
     */
    public function getCertificate(TenantId $tenantId, DossierId $dossierId): CertificateResult;
}

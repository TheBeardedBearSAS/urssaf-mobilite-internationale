<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port;

/**
 * The six operations of the API, backed by the OpenAPI operationId verbatim (api-reference §2).
 */
enum Operation: string
{
    /** POST /enrolements */
    case RequestEnrolment = 'post-enrolement';

    /** GET /enrolements */
    case ListEnrolments = 'get-enrolement-list';

    /** GET /enrolements/{id} */
    case GetEnrolment = 'get-enrolement';

    /** DELETE /enrolements/{id} */
    case DeleteEnrolment = 'delete-enrolement';

    /** POST /declaration/detachement/UE */
    case SubmitPosting = 'post-declaration-grands-comptes';

    /** GET /certificat/{id} */
    case GetCertificate = 'get-certificat';

    /**
     * Published production quota (PDF §2.5), in requests per minute, for the whole API and not per operation. The
     * sandbox quota is not published.
     */
    public const PRODUCTION_QUOTA_PER_MINUTE = 200;

    /**
     * Informational quota for this operation: the production quota of the whole API ({@see self::PRODUCTION_QUOTA_PER_MINUTE}),
     * shared by every operation. It is not enforced unless a {@see RateLimiter} is configured (ADR 0000 row 14).
     */
    public function quotaPerMinute(): int
    {
        return self::PRODUCTION_QUOTA_PER_MINUTE;
    }

    /**
     * True for the two POST operations: no idempotency key, so they are never resent and an uncertain outcome is an
     * {@see Urssaf\OutcomeUnknown} (ADR 0004).
     */
    public function isUnsafeWrite(): bool
    {
        return match ($this) {
            self::RequestEnrolment, self::SubmitPosting => true,
            self::ListEnrolments, self::GetEnrolment, self::DeleteEnrolment, self::GetCertificate => false,
        };
    }
}

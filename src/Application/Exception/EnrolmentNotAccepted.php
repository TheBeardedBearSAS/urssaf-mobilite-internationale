<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Exception;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\DomainException;

/**
 * The optional guard of the posting submission refused the enrolment (ADR 0009, api-reference §4.5): its status is not
 * ACCEPTE, or its SIREN is not the one of the "referenceEntreprise". Thrown before any submission: nothing was sent.
 * The violation code is "enrolment.not_accepted"; the parameter "reason" is "status" or "siren". The message names the
 * enrolment identifier only.
 */
final class EnrolmentNotAccepted extends DomainException
{
    public const CODE = 'enrolment.not_accepted';

    public static function notAccepted(EnrolmentId $enrolmentId): self
    {
        return new self(
            \sprintf('Enrolment "%s" is not accepted: its status is not ACCEPTE.', $enrolmentId->value()),
            self::CODE,
            ['reason' => 'status'],
        );
    }

    public static function otherCompany(EnrolmentId $enrolmentId): self
    {
        return new self(
            \sprintf('Enrolment "%s" is not the enrolment of the referenced company: the SIREN differs.', $enrolmentId->value()),
            self::CODE,
            ['reason' => 'siren'],
        );
    }
}

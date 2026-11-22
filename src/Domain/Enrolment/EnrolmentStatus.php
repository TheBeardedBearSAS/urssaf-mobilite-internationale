<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment;

/**
 * Enrolment status ("StatusEnrolement" schema, api-reference §3.2). Backed by the wire values, verbatim.
 */
enum EnrolmentStatus: string
{
    case Accepte = 'ACCEPTE';
    case Encours = 'ENCOURS';
    case Refuse = 'REFUSE';
    case Supprime = 'SUPPRIME';

    /**
     * Null for a value unknown to this version of the package (the raw value is kept by {@see Enrolment}).
     */
    public static function fromCode(string $code): ?self
    {
        return self::tryFrom($code);
    }
}

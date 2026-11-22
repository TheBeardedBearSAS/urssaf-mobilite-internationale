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
 * Enrolment category ("Categorie" schema, api-reference §3.1). Backed by the wire values, verbatim.
 */
enum EnrolmentCategory: string
{
    case Aa = 'AA';
    case Ae = 'AE';
    case Rg = 'RG';
    case Act = 'ACT';
    case Rgact = 'RGACT';
    case Tiac = 'TIAC';
    case Tipl = 'TIPL';
    case Tiae = 'TIAE';
    case Tipm = 'TIPM';
    case Autre = 'AUTRE';

    /**
     * Null for a value unknown to this version of the package (the raw value is kept by {@see Enrolment}).
     */
    public static function fromCode(string $code): ?self
    {
        return self::tryFrom($code);
    }
}

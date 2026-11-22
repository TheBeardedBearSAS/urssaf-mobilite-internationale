<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityType;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;

/**
 * Visibly synthetic activities.
 */
final class SyntheticActivities
{
    /**
     * Every optional member set (company with a known address).
     */
    public static function complete(string $name, ?ActivityDetails $details): ActivityDeclaration
    {
        return ActivityDeclaration::of(
            ActivityNature::Salarie,
            Country::Allemagne,
            SyntheticParties::knownCompany($name),
            ActivityType::Autre,
            FreeText::fromString($name . ' other nature'),
            Country::Espagne,
            false,
            true,
            false,
            true,
            $details,
        );
    }

    /**
     * Required members only (company without address).
     */
    public static function bare(string $name, ?ActivityDetails $details): ActivityDeclaration
    {
        return ActivityDeclaration::of(
            ActivityNature::Salarie,
            Country::Allemagne,
            SyntheticParties::unknownCompany($name),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $details,
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityNature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityType;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;

/**
 * Reads an activity ("Activite"), fields at "<path>.nature", "<path>.company.email", …
 *
 * @internal
 */
final readonly class ActivityReader
{
    private CompanyReader $companies;

    public function __construct(private DraftReader $reader)
    {
        $this->companies = new CompanyReader($reader);
    }

    public function read(string $path, ActivityDeclarationDraft $draft): ?ActivityDeclaration
    {
        $nature = $this->reader->required($path . '.nature', $draft->nature, ActivityNature::fromWire(...));
        $country = $this->reader->required($path . '.country', $draft->country, Country::fromWire(...));
        $company = $this->companies->required($path . '.company', $draft->company);
        $type = $this->reader->optional($path . '.type', $draft->type, ActivityType::fromWire(...));
        $natureOther = $draft->natureOther === null ? null : FreeText::fromString($draft->natureOther);
        $homeBase = $this->reader->optional($path . '.homeBaseCountry', $draft->homeBaseCountry, Country::fromWire(...));
        if (!$nature instanceof ActivityNature || !$country instanceof Country || !$company instanceof Company) {
            return null;
        }

        return ActivityDeclaration::of(
            $nature,
            $country,
            $company,
            $type,
            $natureOther,
            $homeBase,
            $draft->frenchActivity,
            $draft->atLeast25PercentInFrance,
            $draft->invoiceIssued,
            $draft->contractSigned,
            self::details($draft->details),
        );
    }

    private static function details(?ActivityDetailsDraft $draft): ?ActivityDetails
    {
        return $draft instanceof ActivityDetailsDraft ? ActivityDetails::of(
            $draft->replacesAnotherEmployee,
            $draft->affiliatedToFrenchSocialSecurity,
            $draft->remunerationModifiable,
            $draft->dismissalByHostCompany,
            $draft->disciplinarySanctionsByHostCompany,
            $draft->foreignEmploymentContract,
        ) : null;
    }
}

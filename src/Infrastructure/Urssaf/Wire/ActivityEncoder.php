<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDetails;

/**
 * "Activite" and "activiteDeclareeCplt" schemas of the declaration.
 *
 * @internal
 */
final class ActivityEncoder
{
    /**
     * @return array<string, mixed>|null
     */
    public static function optional(?ActivityDeclaration $activity): ?array
    {
        return $activity instanceof ActivityDeclaration ? self::activity($activity) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function activity(ActivityDeclaration $activity): array
    {
        return WireMembers::present([
            'nature' => $activity->nature()->value,
            'pays' => $activity->country()->value,
            'entreprise' => PartyEncoder::company($activity->company()),
            'type' => $activity->type()?->value,
            'natureAutre' => $activity->natureOther()?->value(),
            'natureBaseAffectation' => $activity->homeBaseCountry()?->value,
            'activiteFrancaise' => $activity->frenchActivity(),
            'activiteSup25' => $activity->atLeast25PercentInFrance(),
            'factureEmise' => $activity->invoiceIssued(),
            'contratSigne' => $activity->contractSigned(),
            'activiteDeclareeCplt' => self::details($activity->details()),
        ]);
    }

    /**
     * @return array<string, mixed>|null null when absent or when no field is set
     */
    private static function details(?ActivityDetails $details): ?array
    {
        if (!$details instanceof ActivityDetails) {
            return null;
        }

        $fields = WireMembers::present([
            'salarieRemplacant' => $details->replacesAnotherEmployee(),
            'affiliationSecuriteSociale' => $details->affiliatedToFrenchSocialSecurity(),
            'remunerationModifiable' => $details->remunerationModifiable(),
            'licenciementSalarie' => $details->dismissalByHostCompany(),
            'sanctionsDisciplinairesSalarie' => $details->disciplinarySanctionsByHostCompany(),
            'contratTravailEstEtranger' => $details->foreignEmploymentContract(),
        ]);

        return $fields === [] ? null : $fields;
    }
}

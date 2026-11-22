<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

/**
 * Complementary information of an activity ("activiteDeclareeCplt"): six optional booleans, null = not sent.
 */
final readonly class ActivityDetails
{
    private function __construct(
        private ?bool $replacesAnotherEmployee,
        private ?bool $affiliatedToFrenchSocialSecurity,
        private ?bool $remunerationModifiable,
        private ?bool $dismissalByHostCompany,
        private ?bool $disciplinarySanctionsByHostCompany,
        private ?bool $foreignEmploymentContract,
    ) {}

    /**
     * Wire names, in order: "salarieRemplacant", "affiliationSecuriteSociale", "remunerationModifiable",
     * "licenciementSalarie", "sanctionsDisciplinairesSalarie", "contratTravailEstEtranger".
     *
     * @mago-expect lint:excessive-parameter-list One argument per field of "activiteDeclareeCplt" (6 fields).
     */
    public static function of(
        ?bool $replacesAnotherEmployee,
        ?bool $affiliatedToFrenchSocialSecurity,
        ?bool $remunerationModifiable,
        ?bool $dismissalByHostCompany,
        ?bool $disciplinarySanctionsByHostCompany,
        ?bool $foreignEmploymentContract,
    ): self {
        return new self(
            $replacesAnotherEmployee,
            $affiliatedToFrenchSocialSecurity,
            $remunerationModifiable,
            $dismissalByHostCompany,
            $disciplinarySanctionsByHostCompany,
            $foreignEmploymentContract,
        );
    }

    public function replacesAnotherEmployee(): ?bool
    {
        return $this->replacesAnotherEmployee;
    }

    public function affiliatedToFrenchSocialSecurity(): ?bool
    {
        return $this->affiliatedToFrenchSocialSecurity;
    }

    public function remunerationModifiable(): ?bool
    {
        return $this->remunerationModifiable;
    }

    public function dismissalByHostCompany(): ?bool
    {
        return $this->dismissalByHostCompany;
    }

    public function disciplinarySanctionsByHostCompany(): ?bool
    {
        return $this->disciplinarySanctionsByHostCompany;
    }

    public function foreignEmploymentContract(): ?bool
    {
        return $this->foreignEmploymentContract;
    }
}

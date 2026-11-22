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
 * Activity ("Activite" schema): the single "activitesDeclarees" object and each item of "activitesActuelles".
 * Required: "nature", "pays", "entreprise". No rule between "nature" and "natureAutre" (ADR 0008). Personal data.
 */
final readonly class ActivityDeclaration
{
    use PersonalData;

    private function __construct(
        private ActivityNature $nature,
        private Country $country,
        private Company $company,
        private ?ActivityType $type,
        private ?FreeText $natureOther,
        private ?Country $homeBaseCountry,
        private ?bool $frenchActivity,
        private ?bool $atLeast25PercentInFrance,
        private ?bool $invoiceIssued,
        private ?bool $contractSigned,
        private ?ActivityDetails $details,
    ) {}

    /**
     * Wire names, in order: "nature", "pays", "entreprise", "type", "natureAutre", "natureBaseAffectation",
     * "activiteFrancaise", "activiteSup25", "factureEmise", "contratSigne", "activiteDeclareeCplt".
     *
     * @mago-expect lint:excessive-parameter-list One argument per field of the "Activite" schema (11 fields).
     */
    public static function of(
        ActivityNature $nature,
        Country $country,
        Company $company,
        ?ActivityType $type,
        ?FreeText $natureOther,
        ?Country $homeBaseCountry,
        ?bool $frenchActivity,
        ?bool $atLeast25PercentInFrance,
        ?bool $invoiceIssued,
        ?bool $contractSigned,
        ?ActivityDetails $details,
    ): self {
        return new self(
            $nature,
            $country,
            $company,
            $type,
            $natureOther,
            $homeBaseCountry,
            $frenchActivity,
            $atLeast25PercentInFrance,
            $invoiceIssued,
            $contractSigned,
            $details,
        );
    }

    public function nature(): ActivityNature
    {
        return $this->nature;
    }

    public function country(): Country
    {
        return $this->country;
    }

    public function company(): Company
    {
        return $this->company;
    }

    public function type(): ?ActivityType
    {
        return $this->type;
    }

    public function natureOther(): ?FreeText
    {
        return $this->natureOther;
    }

    public function homeBaseCountry(): ?Country
    {
        return $this->homeBaseCountry;
    }

    public function frenchActivity(): ?bool
    {
        return $this->frenchActivity;
    }

    public function atLeast25PercentInFrance(): ?bool
    {
        return $this->atLeast25PercentInFrance;
    }

    public function invoiceIssued(): ?bool
    {
        return $this->invoiceIssued;
    }

    public function contractSigned(): ?bool
    {
        return $this->contractSigned;
    }

    public function details(): ?ActivityDetails
    {
        return $this->details;
    }
}

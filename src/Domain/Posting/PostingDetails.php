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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;

/**
 * The posting itself ("demande", "Demande de detachement simplifiee"): every field is optional in the contract
 * (ADR 0008). No order between the start and the end date is checked. Personal data.
 */
final readonly class PostingDetails
{
    use PersonalData;

    /**
     * @param list<ActivityDeclaration> $currentActivities
     */
    private function __construct(
        private ?Worker $worker,
        private ?CalendarDate $startDate,
        private ?CalendarDate $endDate,
        private ?Telework $telework,
        private ?HostAddress $hostAddress,
        private ?Answer $sameActivitySector,
        private ?Answer $durableInfrastructure,
        private ?SubstantialActivity $substantialActivity,
        private ?ActivityDeclaration $declaredActivity,
        private array $currentActivities,
        private ?FreeText $comment,
    ) {}

    /**
     * Wire names, in order: "personne", "dateDebut", "dateFin", "teletravail", "adresseAccueil",
     * "memeDomaineActivite", "infraPerenne", "activiteSubstentielle", "activitesDeclarees" (one object),
     * "activitesActuelles" (a list), "commentaire".
     *
     * @param list<ActivityDeclaration> $currentActivities
     *
     * @mago-expect lint:excessive-parameter-list One argument per field of "demande" (11 fields).
     */
    public static function of(
        ?Worker $worker,
        ?CalendarDate $startDate,
        ?CalendarDate $endDate,
        ?Telework $telework,
        ?HostAddress $hostAddress,
        ?Answer $sameActivitySector,
        ?Answer $durableInfrastructure,
        ?SubstantialActivity $substantialActivity,
        ?ActivityDeclaration $declaredActivity,
        array $currentActivities,
        ?FreeText $comment,
    ): self {
        return new self(
            $worker,
            $startDate,
            $endDate,
            $telework,
            $hostAddress,
            $sameActivitySector,
            $durableInfrastructure,
            $substantialActivity,
            $declaredActivity,
            $currentActivities,
            $comment,
        );
    }

    public function worker(): ?Worker
    {
        return $this->worker;
    }

    public function startDate(): ?CalendarDate
    {
        return $this->startDate;
    }

    public function endDate(): ?CalendarDate
    {
        return $this->endDate;
    }

    public function telework(): ?Telework
    {
        return $this->telework;
    }

    public function hostAddress(): ?HostAddress
    {
        return $this->hostAddress;
    }

    public function sameActivitySector(): ?Answer
    {
        return $this->sameActivitySector;
    }

    public function durableInfrastructure(): ?Answer
    {
        return $this->durableInfrastructure;
    }

    public function substantialActivity(): ?SubstantialActivity
    {
        return $this->substantialActivity;
    }

    public function declaredActivity(): ?ActivityDeclaration
    {
        return $this->declaredActivity;
    }

    /**
     * @return list<ActivityDeclaration>
     */
    public function currentActivities(): array
    {
        return $this->currentActivities;
    }

    public function comment(): ?FreeText
    {
        return $this->comment;
    }
}

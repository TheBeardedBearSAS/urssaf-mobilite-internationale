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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

/**
 * Person in international mobility ("personne", "Personne" schema). Required: "nir", "nomUsage", "prenom",
 * "dateNaissance", "nationalite". Personal data.
 *
 * @mago-expect lint:excessive-parameter-list One argument per field of the "Personne" schema (13 fields).
 */
final readonly class Worker
{
    use PersonalData;

    private function __construct(
        private Nir $nir,
        private ?NirKey $nirKey,
        private Label $usageName,
        private ?Label $birthName,
        private Label $firstName,
        private CalendarDate $birthDate,
        private ?Label $birthPlace,
        private ?Country $birthCountry,
        private Country $nationality,
        private ?Address $address,
        private ?EmailAddress $email,
        private ?PhoneNumber $phone1,
        private ?PhoneNumber $phone2,
    ) {}

    /**
     * Wire names, in order: "nir", "cleNir", "nomUsage", "nomNaissance", "prenom", "dateNaissance", "lieuNaissance",
     * "paysNaissance", "nationalite", "adresse", "email", "tel1", "tel2".
     *
     * @mago-expect lint:excessive-parameter-list One argument per field of the "Personne" schema (13 fields).
     */
    public static function of(
        Nir $nir,
        ?NirKey $nirKey,
        Label $usageName,
        ?Label $birthName,
        Label $firstName,
        CalendarDate $birthDate,
        ?Label $birthPlace,
        ?Country $birthCountry,
        Country $nationality,
        ?Address $address,
        ?EmailAddress $email,
        ?PhoneNumber $phone1,
        ?PhoneNumber $phone2,
    ): self {
        return new self(
            $nir,
            $nirKey,
            $usageName,
            $birthName,
            $firstName,
            $birthDate,
            $birthPlace,
            $birthCountry,
            $nationality,
            $address,
            $email,
            $phone1,
            $phone2,
        );
    }

    public function nir(): Nir
    {
        return $this->nir;
    }

    public function nirKey(): ?NirKey
    {
        return $this->nirKey;
    }

    public function usageName(): Label
    {
        return $this->usageName;
    }

    public function birthName(): ?Label
    {
        return $this->birthName;
    }

    public function firstName(): Label
    {
        return $this->firstName;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function birthPlace(): ?Label
    {
        return $this->birthPlace;
    }

    public function birthCountry(): ?Country
    {
        return $this->birthCountry;
    }

    public function nationality(): Country
    {
        return $this->nationality;
    }

    public function address(): ?Address
    {
        return $this->address;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function phone1(): ?PhoneNumber
    {
        return $this->phone1;
    }

    public function phone2(): ?PhoneNumber
    {
        return $this->phone2;
    }
}

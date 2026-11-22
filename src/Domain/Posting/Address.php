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
 * Postal address ("Adresse" schema): "adresse" and "pays" are required once the address is sent. Personal data.
 */
final readonly class Address
{
    use PersonalData;

    private function __construct(
        private Label $line,
        private ?Label $complement,
        private ?Label $city,
        private ?PostalCode $postalCode,
        private Country $country,
    ) {}

    /**
     * @param Label      $line       "adresse"
     * @param Label|null $complement "adresseCplt"
     * @param Label|null $city       "ville"
     * @param Country    $country    "pays"
     */
    public static function of(Label $line, ?Label $complement, ?Label $city, ?PostalCode $postalCode, Country $country): self
    {
        return new self($line, $complement, $city, $postalCode, $country);
    }

    public function line(): Label
    {
        return $this->line;
    }

    public function complement(): ?Label
    {
        return $this->complement;
    }

    public function city(): ?Label
    {
        return $this->city;
    }

    public function postalCode(): ?PostalCode
    {
        return $this->postalCode;
    }

    public function country(): Country
    {
        return $this->country;
    }
}

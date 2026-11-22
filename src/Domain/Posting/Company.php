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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;

/**
 * Company ("Entreprise" schema): "entrepriseDeclarant" and the company of an activity. Required: "raisonSociale",
 * "adresseConnue", "email". The contract says "Si false, alors le champs adresse est ignore": with an unknown address
 * there is no address ({@see self::withUnknownAddress()}). Personal data (it may identify a self-employed person).
 */
final readonly class Company
{
    use PersonalData;

    private function __construct(
        private Label $name,
        private bool $addressKnown,
        private ?Address $address,
        private EmailAddress $email,
    ) {}

    /**
     * "adresseConnue" true; the address itself stays optional in the contract.
     *
     * @param Label $name "raisonSociale"
     */
    public static function withKnownAddress(Label $name, ?Address $address, EmailAddress $email): self
    {
        return new self($name, true, $address, $email);
    }

    /**
     * "adresseConnue" false: no address is kept.
     *
     * @param Label $name "raisonSociale"
     */
    public static function withUnknownAddress(Label $name, EmailAddress $email): self
    {
        return new self($name, false, null, $email);
    }

    public function name(): Label
    {
        return $this->name;
    }

    public function addressKnown(): bool
    {
        return $this->addressKnown;
    }

    /**
     * Null when absent, always null when the address is unknown.
     */
    public function address(): ?Address
    {
        return $this->address;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }
}

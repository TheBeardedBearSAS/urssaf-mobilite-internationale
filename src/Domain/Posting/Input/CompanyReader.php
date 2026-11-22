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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;

/**
 * Reads a required company ("Entreprise"). The address is not read at all when "addressKnown" is false: the contract
 * ignores it, so it raises no violation.
 *
 * @internal
 */
final readonly class CompanyReader
{
    private AddressReader $addresses;

    public function __construct(private DraftReader $reader)
    {
        $this->addresses = new AddressReader($reader);
    }

    public function required(string $path, ?CompanyDraft $draft): ?Company
    {
        if (!$draft instanceof CompanyDraft) {
            $this->reader->missing($path);

            return null;
        }
        $name = $this->reader->required($path . '.name', $draft->name, Label::fromString(...));
        $addressKnown = $this->reader->flag($path . '.addressKnown', $draft->addressKnown);
        $address = $addressKnown !== false && $draft->address instanceof AddressDraft
            ? $this->addresses->read($path . '.address', $draft->address)
            : null;
        $email = $this->reader->required($path . '.email', $draft->email, EmailAddress::fromString(...));
        if (!$name instanceof Label || $addressKnown === null || !$email instanceof EmailAddress) {
            return null;
        }

        return $addressKnown ? Company::withKnownAddress($name, $address, $email) : Company::withUnknownAddress($name, $email);
    }
}

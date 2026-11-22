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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Nir;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\NirKey;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

/**
 * Reads the optional posted person, fields at "<path>.nir", "<path>.birthDate", "<path>.address.line", …
 *
 * @internal
 */
final readonly class WorkerReader
{
    private AddressReader $addresses;

    public function __construct(private DraftReader $reader)
    {
        $this->addresses = new AddressReader($reader);
    }

    public function read(string $path, ?WorkerDraft $draft): ?Worker
    {
        if (!$draft instanceof WorkerDraft) {
            return null;
        }
        $reader = $this->reader;
        $nir = $reader->required($path . '.nir', $draft->nir, Nir::fromString(...));
        $nirKey = $reader->optional($path . '.nirKey', $draft->nirKey, NirKey::fromString(...));
        $usageName = $reader->required($path . '.usageName', $draft->usageName, Label::fromString(...));
        $birthName = $reader->optional($path . '.birthName', $draft->birthName, Label::fromString(...));
        $firstName = $reader->required($path . '.firstName', $draft->firstName, Label::fromString(...));
        $birthDate = $reader->required($path . '.birthDate', $draft->birthDate, CalendarDate::fromString(...));
        $birthPlace = $reader->optional($path . '.birthPlace', $draft->birthPlace, Label::fromString(...));
        $birthCountry = $reader->optional($path . '.birthCountry', $draft->birthCountry, Country::fromWire(...));
        $nationality = $reader->required($path . '.nationality', $draft->nationality, Country::fromWire(...));
        $address = $draft->address instanceof AddressDraft ? $this->addresses->read($path . '.address', $draft->address) : null;
        $email = $reader->optional($path . '.email', $draft->email, EmailAddress::fromString(...));
        $phone1 = $reader->optional($path . '.phone1', $draft->phone1, PhoneNumber::fromString(...));
        $phone2 = $reader->optional($path . '.phone2', $draft->phone2, PhoneNumber::fromString(...));
        if (!$nir instanceof Nir || !$usageName instanceof Label || !$firstName instanceof Label) {
            return null;
        }
        if (!$birthDate instanceof CalendarDate || !$nationality instanceof Country) {
            return null;
        }

        return Worker::of(
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
}

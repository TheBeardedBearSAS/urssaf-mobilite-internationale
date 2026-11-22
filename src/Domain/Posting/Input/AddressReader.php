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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostalCode;

/**
 * Reads an address draft, fields at "<path>.line", "<path>.country", … Returns null when a required part is invalid.
 *
 * @internal
 */
final readonly class AddressReader
{
    public function __construct(private DraftReader $reader) {}

    public function read(string $path, AddressDraft $draft): ?Address
    {
        $line = $this->reader->required($path . '.line', $draft->line, Label::fromString(...));
        $complement = $this->reader->optional($path . '.complement', $draft->complement, Label::fromString(...));
        $city = $this->reader->optional($path . '.city', $draft->city, Label::fromString(...));
        $postalCode = $this->reader->optional($path . '.postalCode', $draft->postalCode, PostalCode::fromString(...));
        $country = $this->reader->required($path . '.country', $draft->country, Country::fromWire(...));
        if (!$line instanceof Label || !$country instanceof Country) {
            return null;
        }

        return Address::of($line, $complement, $city, $postalCode, $country);
    }
}

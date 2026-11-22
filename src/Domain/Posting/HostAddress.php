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
 * Address of the worker during the posting ("adresseAccueil"): "adresseHabituelle" required. When the worker keeps
 * their usual home ("adresseHabituelle" true) the contract ignores the address, so none is kept
 * ({@see self::usualHome()}). Personal data.
 */
final readonly class HostAddress
{
    use PersonalData;

    private function __construct(private bool $usualAddress, private ?Address $address) {}

    /**
     * "adresseHabituelle" true: the worker keeps their usual home (e.g. cross-border workers), no address is kept.
     */
    public static function usualHome(): self
    {
        return new self(true, null);
    }

    /**
     * "adresseHabituelle" false; the address itself stays optional in the contract.
     */
    public static function elsewhere(?Address $address): self
    {
        return new self(false, $address);
    }

    public function usualAddress(): bool
    {
        return $this->usualAddress;
    }

    /**
     * Null when absent, always null for the usual home.
     */
    public function address(): ?Address
    {
        return $this->address;
    }
}

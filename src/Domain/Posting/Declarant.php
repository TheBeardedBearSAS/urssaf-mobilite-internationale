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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;

/**
 * Declarant contact ("declarant", sibling of "declaration"): "nom", "email", "tel1" required, "tel2" optional. Optional
 * in the Swagger, required by the package (PDF §3.3.1, ADR 0008). Personal data.
 */
final readonly class Declarant
{
    use PersonalData;

    private function __construct(
        private Label $name,
        private EmailAddress $email,
        private PhoneNumber $phone1,
        private ?PhoneNumber $phone2,
    ) {}

    /**
     * @param Label            $name   "nom"
     * @param PhoneNumber      $phone1 "tel1"
     * @param PhoneNumber|null $phone2 "tel2"
     */
    public static function of(Label $name, EmailAddress $email, PhoneNumber $phone1, ?PhoneNumber $phone2): self
    {
        return new self($name, $email, $phone1, $phone2);
    }

    public function name(): Label
    {
        return $this->name;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function phone1(): PhoneNumber
    {
        return $this->phone1;
    }

    public function phone2(): ?PhoneNumber
    {
        return $this->phone2;
    }
}

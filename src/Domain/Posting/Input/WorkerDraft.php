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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PersonalData;

/**
 * Raw posted person ("personne"), same fields and order as {@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker::of()}.
 * Dates "Y-m-d", countries as wire values.
 */
final readonly class WorkerDraft
{
    use PersonalData;

    /**
     * @mago-expect lint:excessive-parameter-list One argument per field of the "Personne" schema (13 fields).
     */
    public function __construct(
        public ?string $nir,
        public ?string $nirKey,
        public ?string $usageName,
        public ?string $birthName,
        public ?string $firstName,
        public ?string $birthDate,
        public ?string $birthPlace,
        public ?string $birthCountry,
        public ?string $nationality,
        public ?AddressDraft $address,
        public ?string $email,
        public ?string $phone1,
        public ?string $phone2,
    ) {}
}

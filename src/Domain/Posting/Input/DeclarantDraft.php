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
 * Raw "declarant": "name" = "nom", "phone1" / "phone2" = "tel1" / "tel2".
 */
final readonly class DeclarantDraft
{
    use PersonalData;

    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $phone1,
        public ?string $phone2,
    ) {}
}

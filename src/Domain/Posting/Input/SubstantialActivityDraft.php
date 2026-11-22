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

/**
 * Raw "activiteSubstentielle": "answer" = "activiteSubstentielle" ("NC" / "OUI" / "NON"), "countryOfInterest" = "paysInteret", "criterion" = "critereDActivite".
 */
final readonly class SubstantialActivityDraft
{
    public function __construct(
        public ?string $answer,
        public ?string $countryOfInterest,
        public ?string $criterion,
    ) {}
}

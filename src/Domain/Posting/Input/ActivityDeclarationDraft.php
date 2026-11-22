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
 * Raw "Activite", same fields and order as {@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration::of()}; enums and countries as wire values.
 */
final readonly class ActivityDeclarationDraft
{
    use PersonalData;

    public function __construct(
        public ?string $nature,
        public ?string $country,
        public ?CompanyDraft $company,
        public ?string $type,
        public ?string $natureOther,
        public ?string $homeBaseCountry,
        public ?bool $frenchActivity,
        public ?bool $atLeast25PercentInFrance,
        public ?bool $invoiceIssued,
        public ?bool $contractSigned,
        public ?ActivityDetailsDraft $details,
    ) {}
}

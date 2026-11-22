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
 * Raw "declaration": "declaringCompany" = "entrepriseDeclarant", "companyReference" = "referenceEntreprise", "posting" = "demande".
 */
final readonly class DeclarationDraft
{
    use PersonalData;

    public function __construct(
        public ?CompanyDraft $declaringCompany,
        public ?CompanyReferenceDraft $companyReference,
        public ?SignatureDraft $signature,
        public ?PostingDetailsDraft $posting,
    ) {}
}

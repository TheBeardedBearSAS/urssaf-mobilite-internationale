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
 * Raw body of the posting request, read by {@see PostingRequestReader}. Every value is raw (strings as sent on the wire, booleans, integers); null = absent, "" is a value. Violation paths use these English property names, e.g. "declaration.posting.worker.nir".
 */
final readonly class PostingRequestDraft
{
    use PersonalData;

    public function __construct(
        public ?DeclarantDraft $declarant,
        public ?DeclarationDraft $declaration,
    ) {}
}

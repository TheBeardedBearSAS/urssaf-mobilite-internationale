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
 * Raw "demande", same fields and order as {@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails::of()}; dates "Y-m-d", answers "NC" / "OUI" / "NON".
 */
final readonly class PostingDetailsDraft
{
    use PersonalData;

    /**
     * @param list<ActivityDeclarationDraft> $currentActivities
     */
    public function __construct(
        public ?WorkerDraft $worker,
        public ?string $startDate,
        public ?string $endDate,
        public ?TeleworkDraft $telework,
        public ?HostAddressDraft $hostAddress,
        public ?string $sameActivitySector,
        public ?string $durableInfrastructure,
        public ?SubstantialActivityDraft $substantialActivity,
        public ?ActivityDeclarationDraft $declaredActivity,
        public array $currentActivities,
        public ?string $comment,
    ) {}
}

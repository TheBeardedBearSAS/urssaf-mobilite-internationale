<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf;

/**
 * Answer of "GET /certificat/{id}" (ADR 0003): {@see CertificateAvailable} | {@see CertificateInProgress} |
 * {@see CertificateRefusedOrUnknown}. "Not ready yet" and "refused" are results, never exceptions; an exception means a
 * real failure. Placed in the Application layer because its variants carry port types.
 */
interface CertificateResult
{
    /**
     * The raw HTTP status URSSAF answered.
     */
    public function httpStatus(): int;
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Query;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;

/**
 * "Not ready yet" and "refused" are results, not exceptions (ADR 0003); the certificate content is a stream that the
 * caller reads and closes (ADR 0005).
 */
final readonly class GetCertificateHandler
{
    public function __construct(private MobilityGateway $gateway) {}

    public function __invoke(GetCertificate $query): CertificateResult
    {
        return $this->gateway->getCertificate($query->tenantId, $query->dossierId);
    }
}

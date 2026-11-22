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
 * HTTP 404: the dossier was refused (PDF: no certificate will be issued, contact the case manager) or does not exist
 * (Swagger). The package cannot tell the two readings apart (ADR 0003). Not a "retry later".
 */
final readonly class CertificateRefusedOrUnknown implements CertificateResult
{
    private function __construct(private int $httpStatus, private ApiErrors $errors) {}

    public static function of(int $httpStatus, ApiErrors $errors): self
    {
        return new self($httpStatus, $errors);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }
}

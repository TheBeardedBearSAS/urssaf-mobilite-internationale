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
 * The dossier exists and is being instructed, no certificate yet: call again later. HTTP 204 (Swagger, no body) and,
 * by default, HTTP 400 (PDF; configurable in the Infrastructure gateway options, ADR 0003).
 */
final readonly class CertificateInProgress implements CertificateResult
{
    private function __construct(private int $httpStatus, private ApiErrors $errors) {}

    /**
     * @param ApiErrors $errors the raw parsed errors ({@see ApiErrors::none()} for a 204)
     */
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

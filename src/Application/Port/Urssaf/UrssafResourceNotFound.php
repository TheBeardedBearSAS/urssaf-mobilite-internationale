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

use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

/**
 * URSSAF answered 404 on an enrolment operation: no such enrolment for this issuer (ADR 0002). Not retryable. The raw
 * errors are kept, never put in the message. The certificate 404 is a {@see CertificateRefusedOrUnknown} result.
 */
final class UrssafResourceNotFound extends RuntimeException implements UrssafException
{
    private const HTTP_STATUS = 404;

    private function __construct(string $message, private readonly Operation $operation, private readonly ApiErrors $errors)
    {
        parent::__construct($message, self::HTTP_STATUS);
    }

    public static function withErrors(Operation $operation, ApiErrors $errors): self
    {
        return new self(
            \sprintf('URSSAF operation %s found no such resource (HTTP 404).', $operation->value),
            $operation,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return self::HTTP_STATUS;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }
}

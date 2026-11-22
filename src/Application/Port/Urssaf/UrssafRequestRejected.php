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
 * URSSAF refused the call (ADR 0002): HTTP 400 on every operation, and HTTP 403 on {@see Operation::SubmitPosting}
 * ("not authorised to declare for this company", a business refusal, not a credential failure). Nothing was
 * recorded by URSSAF; not retryable as is.
 *
 * The raw errors are kept in {@see self::errors()} for the caller; the message is built from the operation and the
 * status only and never contains them (ADR 0006).
 */
final class UrssafRequestRejected extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $httpStatus,
        private readonly ApiErrors $errors,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function withErrors(Operation $operation, int $httpStatus, ApiErrors $errors): self
    {
        return new self(
            \sprintf('URSSAF operation %s rejected the request (HTTP %d).', $operation->value, $httpStatus),
            $operation,
            $httpStatus,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code): bool
    {
        return $this->errors->hasCode($code);
    }
}

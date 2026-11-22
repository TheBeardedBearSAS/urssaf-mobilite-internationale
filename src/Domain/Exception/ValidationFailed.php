<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;

/**
 * Thrown when a validation result containing violations must stop the current use case. Carries every violation.
 */
final class ValidationFailed extends DomainException
{
    private function __construct(private readonly ValidationResult $result)
    {
        $errorCount = \count($result->all());
        parent::__construct(
            \sprintf('Validation failed with %d error(s).', $errorCount),
            'validation.failed',
            ['error_count' => $errorCount],
        );
    }

    public static function withResult(ValidationResult $result): self
    {
        return new self($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}

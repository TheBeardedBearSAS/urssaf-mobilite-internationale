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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

/**
 * Base class of every domain exception. Each one carries a stable violation code "<subject>.<reason>"
 * (e.g. "siren.invalid_length") and scalar parameters, and can be converted into a validation violation.
 *
 * Messages are English and never contain personal data nor the rejected raw value.
 */
abstract class DomainException extends \DomainException implements UrssafException
{
    /**
     * @param array<string, scalar> $params
     */
    protected function __construct(string $message, private readonly string $violationCode, private readonly array $params)
    {
        parent::__construct($message);
    }

    final public function violationCode(): string
    {
        return $this->violationCode;
    }

    /**
     * @return array<string, scalar>
     */
    final public function params(): array
    {
        return $this->params;
    }

    /**
     * Converts the exception into a violation located at the given path (empty path = root).
     */
    final public function toViolation(string $path): Violation
    {
        return Violation::error($path, $this->violationCode, $this->getMessage(), $this->params);
    }
}

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

/**
 * Thrown when an operation would break a business invariant of a value object or of an object built from value objects.
 */
final class InvariantViolation extends DomainException
{
    /**
     * @param array<string, scalar> $params
     */
    public static function because(string $violationCode, string $message, array $params = []): self
    {
        return new self($message, $violationCode, $params);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment;

use Stringable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Identifier of an enrolment ("idEnrolement", "format: uuid"). Any UUID version; normalised to lower case.
 */
final readonly class EnrolmentId implements Stringable
{
    private const SUBJECT = 'enrolment_id';
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "enrolment_id.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'UUID');
        }

        return new self(strtolower($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

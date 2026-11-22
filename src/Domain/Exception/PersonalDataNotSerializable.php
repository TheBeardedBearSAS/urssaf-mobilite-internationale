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

use LogicException;

/**
 * Thrown when an object carrying personal data is serialized or unserialized (ADR 0006): such data must never be
 * written to a cache, a session or a message queue by accident.
 */
final class PersonalDataNotSerializable extends LogicException implements UrssafException
{
    public static function create(): self
    {
        return new self('Objects carrying personal data must never be serialized.');
    }
}

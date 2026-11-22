<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

final class ViolationSummary
{
    /**
     * @return list<string> "<path> <code>" of every violation, in order
     */
    public static function of(ValidationResult $result): array
    {
        return array_map(static fn(Violation $violation): string => $violation->path() . ' ' . $violation->code(), $result->all());
    }
}

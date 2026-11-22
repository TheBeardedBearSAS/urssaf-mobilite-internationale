<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Tri-state answer "NC" / "OUI" / "NON" (api-reference §3.4). Never mapped to a boolean: "NC" (not concerned) is not "NON".
 */
enum Answer: string
{
    case NotApplicable = 'NC';
    case Yes = 'OUI';
    case No = 'NON';

    /**
     * @throws InvalidValue "answer.unknown" for any value not in the contract (the value is never repeated)
     */
    public static function fromWire(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidValue::because('answer.unknown', 'Invalid answer: the value is not in the list of the contract.');
    }
}

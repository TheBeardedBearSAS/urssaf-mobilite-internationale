<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

final class JsonEncoder
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Compact JSON, slashes and Unicode left as they are. A list encodes as an array, any other array as an object;
     * a {@see JsonObject}, at any depth, encodes as an object even when empty (`{}`).
     *
     * @param array<array-key, mixed>|JsonObject $value
     *
     * @throws InvalidValue "payload.invalid_encoding" for an invalid UTF-8 string or a value JSON cannot represent; the
     *                      message never contains the value (ADR 0006)
     */
    public static function encode(#[SensitiveParameter] array|JsonObject $value): string
    {
        $json = json_encode($value, self::FLAGS);
        if ($json === false) {
            throw InvalidValue::because(
                'payload.invalid_encoding',
                'Invalid payload: a text is not valid UTF-8 and cannot be encoded as JSON.',
            );
        }

        return $json;
    }
}

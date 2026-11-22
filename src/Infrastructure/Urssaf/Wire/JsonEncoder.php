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

use JsonException;

final class JsonEncoder
{
    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Compact JSON, slashes and Unicode left as they are. A list encodes as an array, any other array as an object;
     * a {@see JsonObject}, at any depth, encodes as an object even when empty (`{}`).
     *
     * @param array<array-key, mixed>|JsonObject $value
     *
     * @throws JsonException for invalid UTF-8 strings or a value JSON cannot represent
     */
    public static function encode(array|JsonObject $value): string
    {
        return \strval(json_encode($value, self::FLAGS));
    }
}

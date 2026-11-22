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

final class JsonDecoder
{
    /**
     * @throws UnreadablePayload when the body is not valid JSON or not a JSON object / array
     */
    public static function decode(string $json): Payload
    {
        try {
            return Payload::fromDecoded(json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        } catch (JsonException $exception) {
            throw UnreadablePayload::causedBy('body is not valid JSON', $exception);
        }
    }
}

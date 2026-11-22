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

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;

/**
 * Reads an URSSAF error body (api-reference §1.3): a single object (most operations), a list of objects (the 400 of the
 * declaration), or anything else (empty body of the declaration 401 / 500, HTML of a gateway, ...). The fields are
 * raw: the semantics of `code`, `message` and `description` are not published, so nothing is reinterpreted.
 *
 * The result is for the caller of the SDK only; it is never logged nor put in an exception message (ADR 0006).
 */
final class ErrorPayloadParser
{
    /**
     * Lenient: a body that is not readable as errors yields {@see ApiErrors::none()}, never an exception.
     * A list with an element that is not an object is unreadable as a whole. An object carrying none of the three
     * fields yields no error.
     */
    public function parse(string $body): ApiErrors
    {
        try {
            $payload = JsonDecoder::decode($body);
            $entries = $payload->isList() ? $payload->entries() : [$payload];
        } catch (UnreadablePayload) {
            return ApiErrors::none();
        }

        $errors = [];
        foreach ($entries as $entry) {
            $code = $entry->string('code');
            $message = $entry->string('message');
            $description = $entry->string('description');
            if ($code !== null || $message !== null || $description !== null) {
                $errors[] = ApiError::of($code, $message, $description);
            }
        }

        return ApiErrors::of(...$errors);
    }
}

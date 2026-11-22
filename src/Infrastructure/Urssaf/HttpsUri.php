<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Check of an overridden URI (ADR 0002): absolute, without user info, query or fragment, and `https` only. `http` is
 * refused even for a local host (a mock server needs a TLS certificate), so that the client secret and the token never
 * travel in clear (closes the CESU / PAJE finding SR-01).
 *
 * @internal
 */
final class HttpsUri
{
    /**
     * @param string $subject snake_case name of the setting, prefix of the violation code
     *
     * @throws InvalidValue "<subject>.invalid_format" or "<subject>.not_https"
     */
    public static function assert(string $subject, string $uri): void
    {
        $parts = parse_url($uri);
        $scheme = $parts === false ? '' : ($parts['scheme'] ?? '');
        $host = $parts === false ? '' : ($parts['host'] ?? '');
        if (
            $parts === false
            || preg_match('/[\s?#]/', $uri) === 1
            || $scheme === ''
            || $host === ''
            || \array_key_exists('user', $parts)
        ) {
            throw InvalidValue::invalidFormat($subject, 'absolute URI without user info, query or fragment');
        }

        if (strtolower($scheme) !== 'https') {
            throw InvalidValue::because(
                $subject . '.not_https',
                \sprintf('Invalid %s: the URI must use the https scheme.', $subject),
            );
        }
    }
}

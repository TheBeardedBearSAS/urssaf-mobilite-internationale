<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port;

use SensitiveParameter;

/**
 * Cache of access tokens, the only shared state of the package (ADR 0001). Keys are opaque strings built by the
 * authenticator (ADR 0007: never the secret); implementations must not log token values.
 */
interface TokenStore
{
    public function get(string $key): ?AccessToken;

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void;

    public function delete(string $key): void;
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic\Fake;

use SensitiveParameter;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\AccessToken;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;

/**
 * A faulty store that forgets everything.
 */
final class AmnesicTokenStore implements TokenStore
{
    public function get(string $key): ?AccessToken
    {
        return null;
    }

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void {}

    public function delete(string $key): void {}
}

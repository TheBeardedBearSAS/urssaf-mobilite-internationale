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
 * A faulty store that never deletes.
 */
final class StickyTokenStore implements TokenStore
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function get(string $key): ?AccessToken
    {
        return $this->tokens[$key] ?? null;
    }

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    public function delete(string $key): void {}

    /**
     * @return array<string, AccessToken>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic;

use DateInterval;
use Override;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\AccessToken;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use Throwable;

/**
 * The access tokens live in a store shared by every process: a probe token is saved, read back and deleted. The probe
 * has its own key and a short lifetime, so the tokens of the application are left untouched.
 */
final readonly class TokenStoreCheck implements InstallationCheck
{
    private const NAME = 'token store';
    private const PROBE_KEY = 'urssaf_mi.check';
    private const PROBE_VALUE = 'probe';
    private const PROBE_LIFETIME = 'PT60S';

    public function __construct(private TokenStore $tokens, private Clock $clock, private bool $sharedPool = false) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        try {
            $this->tokens->save(self::PROBE_KEY, AccessToken::of(self::PROBE_VALUE, $this->clock->now()->add(new DateInterval(self::PROBE_LIFETIME))));
            $saved = $this->tokens->get(self::PROBE_KEY);
            $this->tokens->delete(self::PROBE_KEY);
            $remaining = $this->tokens->get(self::PROBE_KEY);
        } catch (Throwable $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, \sprintf('The token store failed (%s): check its configuration and availability.', $failure::class))];
        }

        if ($saved?->value() !== self::PROBE_VALUE) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, 'A saved token cannot be read back: the store does not keep tokens between calls.')];
        }
        if ($remaining instanceof AccessToken) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, 'A deleted token is still readable: the store does not delete tokens.')];
        }

        if ($this->sharedPool) {
            return [new CheckResult(self::NAME, '', CheckStatus::Warning, 'A token can be saved, read and deleted, but the pool "cache.app" is shared with the application: declare a dedicated pool, so that clearing the application cache does not drop the tokens (docs/symfony.md).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'A token can be saved, read and deleted.')];
    }
}

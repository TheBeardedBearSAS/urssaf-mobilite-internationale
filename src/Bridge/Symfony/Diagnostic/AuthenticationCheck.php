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

use Closure;
use Override;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use ValueError;

/**
 * URSSAF grants an access token to every tenant: any cached token is dropped first, so that it never hides refused
 * credentials. The token provider given here must use a token store of its own (the bundle gives it an in-memory one):
 * the tokens of the application are left untouched. The provider is built by a closure, inside the check, so that an
 * invalid configuration is a failed check (the "configuration" check says why) instead of a stack trace.
 */
final readonly class AuthenticationCheck implements InstallationCheck
{
    private const NAME = 'authentication';

    /**
     * @param Closure(): OAuth2TokenProvider $tokens
     */
    public function __construct(private CredentialsProvider $credentials, private Closure $tokens) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if (!$context->remoteCallsAllowed) {
            return [new CheckResult(self::NAME, '', CheckStatus::Skipped, 'Not checked (--skip-remote).')];
        }
        // @mago-expect analysis:avoid-catching-error Environment::from() throws a ValueError for an unknown environment: it is reported by the check, not a crash.
        try {
            $tokens = ($this->tokens)();
        } catch (ValueError|UrssafException $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, $failure->getMessage())];
        }

        return array_map(fn(TenantId $tenantId): CheckResult => $this->check($tokens, $tenantId), $context->tenants);
    }

    private function check(OAuth2TokenProvider $tokens, TenantId $tenantId): CheckResult
    {
        try {
            $credentials = $this->credentials->forTenant($tenantId);
            $tokens->invalidate($credentials);
            $tokens->accessToken($credentials, Operation::ListEnrolments);
        } catch (UrssafException $failure) {
            return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, $failure->getMessage());
        }

        return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Ok, 'Access token granted by URSSAF.');
    }
}

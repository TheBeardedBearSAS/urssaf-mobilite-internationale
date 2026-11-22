<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenCacheTest extends TestCase
{
    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    private const ISSUED = 'fictitious-access-token';

    public function testItCachesTheTokenUnderAPsr6SafeKeyWithoutSecrets(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();

        $provider->accessToken($this->credentials(), Operation::GetEnrolment);
        $second = $provider->accessToken($this->credentials(), Operation::ListEnrolments);

        static::assertSame(self::ISSUED, $second);
        static::assertCount(1, $stack->requests());
        $expectedKey = 'urssaf_mi.' . hash('sha256', 'sandbox|https://api-edi.urssaf.fr/tiersdecl/v1/mobilite'
            . '|https://api-edi.urssaf.fr/api/oauth/v1/token|ods.dlagd|' . StaticCredentialsProvider::CLIENT_ID);
        static::assertSame([$expectedKey], $stack->tokens->keys());
        static::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/', $expectedKey);
    }

    public function testTheCacheKeyDependsOnClientEnvironmentBaseUriAndTokenUri(): void
    {
        $keys = [];
        $variants = [
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-a'],
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-b'],
            [ApiConfiguration::forEnvironment(Environment::Production), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, 'https://proxy.example.test', null, null), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, null, 'https://token.example.test/token', null), 'client-a'],
        ];
        foreach ($variants as [$configuration, $clientId]) {
            $stack = Stacks::configured($configuration);
            $stack->respondToken();
            $stack->tokenProvider()->accessToken($this->credentials($clientId), Operation::GetEnrolment);
            $keys[] = $stack->tokens->keys()[0];
        }

        static::assertCount(5, array_unique($keys));
    }

    public function testInvalidateForcesANewToken(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetEnrolment);

        $provider->invalidate($this->credentials());

        static::assertSame([], $stack->tokens->keys());
        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetEnrolment));
    }

    public function testInvalidateOnlyAffectsTheGivenClient(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials('client-a'), Operation::GetEnrolment);
        $provider->accessToken($this->credentials('client-b'), Operation::GetEnrolment);

        $provider->invalidate($this->credentials('client-a'));

        static::assertCount(1, $stack->tokens->keys());
    }
}

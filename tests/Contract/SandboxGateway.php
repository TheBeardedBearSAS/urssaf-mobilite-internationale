<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Wires the real HTTP gateway against the URSSAF sandbox from environment variables:
 *
 * - URSSAF_SANDBOX_CLIENT_ID / URSSAF_SANDBOX_CLIENT_SECRET (required, otherwise the suite is skipped);
 * - URSSAF_SANDBOX_BASE_URI (optional override of the API base URI, path included);
 * - URSSAF_SANDBOX_TOKEN_URI (optional override of the OAuth2 token URI: the sandbox token host is not published,
 *   see docs/api-reference.md section 5).
 */
final readonly class SandboxGateway
{
    public const TENANT = 'contract-tests';

    private function __construct(
        public MobilityGateway $gateway,
        public OAuth2TokenProvider $tokenProvider,
        public Credentials $credentials,
        public TenantId $tenantId,
    ) {}

    /**
     * Null when the required environment variables are missing.
     */
    public static function fromEnvironment(): ?self
    {
        $clientId = self::env('URSSAF_SANDBOX_CLIENT_ID');
        $clientSecret = self::env('URSSAF_SANDBOX_CLIENT_SECRET');
        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $tenantId = TenantId::fromString(self::TENANT);
        $credentials = Credentials::of($clientId, $clientSecret);
        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            self::env('URSSAF_SANDBOX_BASE_URI'),
            self::env('URSSAF_SANDBOX_TOKEN_URI'),
            null,
        );

        $factory = new Psr17Factory();
        $transport = new HttpTransport(new Psr18Client(HttpClient::create(['timeout' => 30]), $factory, $factory), $factory, $factory);

        return new self(
            UrssafGatewayFactory::create(
                $transport,
                new InMemoryCredentialsProvider([$tenantId->value() => $credentials]),
                new InMemoryTokenStore(),
                $configuration,
            ),
            new OAuth2TokenProvider(
                $transport->client(),
                $transport->requestFactory(),
                $transport->streamFactory(),
                new InMemoryTokenStore(),
                new UtcSystemClock(),
                $configuration,
            ),
            $credentials,
            $tenantId,
        );
    }

    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

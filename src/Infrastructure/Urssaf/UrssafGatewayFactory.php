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

use Psr\Log\NullLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\RequestBuilder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ResponseInterpreter;

/**
 * Public entry point to build the URSSAF HTTP gateway outside a framework bridge:
 *
 *     $gateway = UrssafGatewayFactory::createWith(
 *         new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
 *         $credentialsProvider,
 *         $tokenStore,
 *         ApiConfiguration::forEnvironment(Environment::Production),
 *         GatewayOptions::defaults()->withLogger($logger),
 *     );
 *
 * {@see self::create()} takes the same arguments without the options.
 *
 * The OAuth2 token provider and the HTTP client it wires are internal.
 */
final class UrssafGatewayFactory
{
    /**
     * Gateway with the default options ({@see GatewayOptions::defaults()}).
     */
    public static function create(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
    ): UrssafHttpGateway {
        return self::createWith($transport, $credentials, $tokens, $configuration, GatewayOptions::defaults());
    }

    public static function createWith(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
        GatewayOptions $options,
    ): UrssafHttpGateway {
        $clock = $options->clock() ?? new UtcSystemClock();
        $logger = $options->logger() ?? new NullLogger();
        $tokenProvider = new OAuth2TokenProvider(
            $transport->client(),
            $transport->requestFactory(),
            $transport->streamFactory(),
            $tokens,
            $clock,
            $configuration,
            $logger,
        );
        $client = new JsonApiClient(
            $transport->client(),
            $transport->streamFactory(),
            $tokenProvider,
            new RequestBuilder($transport->requestFactory(), $transport->streamFactory(), $configuration),
            new ResponseInterpreter(),
            $options->rateLimiter() ?? new NoopRateLimiter(),
            $options->idGenerator() ?? new RandomUuidGenerator(new SecureRandomBytes()),
            $clock,
            $configuration,
            $logger,
        );

        return new UrssafHttpGateway($credentials, $client, $options->certificateBadRequestAsInProgress());
    }
}

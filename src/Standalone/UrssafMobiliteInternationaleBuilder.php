<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Standalone;

use LogicException;
use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;

/**
 * Wires the package without a framework. Every "with" method returns a new builder.
 *
 * Defaults: in-memory token store (use a store shared by every process in production), the UTC system clock, no
 * logging, no rate limiting, the default posting validation policy, the URSSAF URIs of the environment.
 *
 * A custom gateway ({@see self::withGateway()}, for instance the Testing\SimulatedMobilityGateway) is refused for the
 * production environment, whatever the order of the calls (ADR 0010).
 *
 * @mago-expect lint:too-many-methods An immutable builder: one "with" method per collaborator or option.
 */
final readonly class UrssafMobiliteInternationaleBuilder
{
    private function __construct(
        private HttpTransport $transport,
        private CredentialsProvider $credentials,
        private Environment $environment,
        private ?string $baseUri,
        private ?string $tokenUri,
        private TokenStore $tokenStore,
        private GatewayOptions $options,
        private PostingValidationPolicy $policy,
        private ?MobilityGateway $gateway,
    ) {
        if ($gateway instanceof MobilityGateway && $environment === Environment::Production) {
            throw new LogicException('A custom gateway is meant for tests and cannot be used with the production environment.');
        }
    }

    public static function create(HttpTransport $transport, CredentialsProvider $credentials, Environment $environment): self
    {
        return new self(
            $transport,
            $credentials,
            $environment,
            null,
            null,
            new InMemoryTokenStore(),
            GatewayOptions::defaults(),
            PostingValidationPolicy::default(),
            null,
        );
    }

    /**
     * @throws LogicException when a custom gateway was already given and the environment is production
     */
    public function withEnvironment(Environment $environment): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $environment,
            $this->baseUri,
            $this->tokenUri,
            $this->tokenStore,
            $this->options,
            $this->policy,
            $this->gateway,
        );
    }

    /**
     * Overrides the base URI of the API; checked by {@see self::build()} (https only, ADR 0002).
     */
    public function withBaseUri(string $baseUri): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $baseUri,
            $this->tokenUri,
            $this->tokenStore,
            $this->options,
            $this->policy,
            $this->gateway,
        );
    }

    /**
     * Overrides the OAuth2 token URI (the sandbox token host is not published, ADR 0002); checked by
     * {@see self::build()} (https only).
     */
    public function withTokenUri(string $tokenUri): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $this->baseUri,
            $tokenUri,
            $this->tokenStore,
            $this->options,
            $this->policy,
            $this->gateway,
        );
    }

    /**
     * A token store shared by every process that calls URSSAF (PSR-6 / PSR-16 store).
     */
    public function withTokenStore(TokenStore $tokenStore): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $this->baseUri,
            $this->tokenUri,
            $tokenStore,
            $this->options,
            $this->policy,
            $this->gateway,
        );
    }

    /**
     * The optional collaborators of the HTTP gateway (rate limiter, id generator, clock, logger, certificate 400).
     */
    public function withOptions(GatewayOptions $options): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $this->baseUri,
            $this->tokenUri,
            $this->tokenStore,
            $options,
            $this->policy,
            $this->gateway,
        );
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->withOptions($this->options->withLogger($logger));
    }

    public function withClock(Clock $clock): self
    {
        return $this->withOptions($this->options->withClock($clock));
    }

    /**
     * The opt-in local rules applied by the posting handler before the call (ADR 0008).
     */
    public function withValidationPolicy(PostingValidationPolicy $policy): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $this->baseUri,
            $this->tokenUri,
            $this->tokenStore,
            $this->options,
            $policy,
            $this->gateway,
        );
    }

    /**
     * Replaces the HTTP gateway (for instance with the Testing\SimulatedMobilityGateway in tests): the HTTP gateway is
     * then not built.
     *
     * @throws LogicException for the production environment
     */
    public function withGateway(MobilityGateway $gateway): self
    {
        return new self(
            $this->transport,
            $this->credentials,
            $this->environment,
            $this->baseUri,
            $this->tokenUri,
            $this->tokenStore,
            $this->options,
            $this->policy,
            $gateway,
        );
    }

    /**
     * @throws InvalidValue "base_uri" or "token_uri" not an https URI
     */
    public function build(): UrssafMobiliteInternationale
    {
        return new UrssafMobiliteInternationale($this->gateway ?? $this->httpGateway(), $this->policy);
    }

    private function httpGateway(): MobilityGateway
    {
        return UrssafGatewayFactory::createWith(
            $this->transport,
            $this->credentials,
            $this->tokenStore,
            new ApiConfiguration($this->environment, $this->baseUri, $this->tokenUri, null),
            $this->options,
        );
    }
}

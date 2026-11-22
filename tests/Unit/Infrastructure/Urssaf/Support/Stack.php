<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\RequestBuilder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ResponseInterpreter;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * Wiring of the Urssaf infrastructure on top of a PSR-18 mock client and in-memory fakes.
 *
 * @mago-expect lint:too-many-methods Test fixture: one method per collaborator or scripted answer; splitting it would only scatter the wiring that every HTTP test needs.
 */
final readonly class Stack
{
    public const ISSUED = 'fictitious-access-token';

    public Client $http;
    public InMemoryTokenStore $tokens;
    public RecordingRateLimiter $rateLimiter;
    public SteppingClock $clock;
    public CapturingLogger $logger;
    public StaticCredentialsProvider $credentials;
    public SequenceIdGenerator $ids;
    private Psr17Factory $factory;

    public function __construct(public ApiConfiguration $configuration, int $clockStepMicroseconds)
    {
        $this->http = new Client();
        $this->tokens = new InMemoryTokenStore();
        $this->rateLimiter = new RecordingRateLimiter();
        $this->clock = new SteppingClock($clockStepMicroseconds);
        $this->logger = new CapturingLogger();
        $this->credentials = new StaticCredentialsProvider();
        $this->factory = new Psr17Factory();
        $this->ids = new SequenceIdGenerator();
    }

    /**
     * Fetches and caches a token now, so that a later failure queued on the mock client hits the API call itself.
     */
    public function warmUpToken(): void
    {
        $this->respondToken();
        $this->tokenProvider()->accessToken($this->credentials->forTenant(Stacks::tenant()), Operation::GetEnrolment);
        $this->logger->clear();
    }

    public function tokenProvider(): OAuth2TokenProvider
    {
        return new OAuth2TokenProvider(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokens,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    public function jsonClient(): JsonApiClient
    {
        return new JsonApiClient(
            $this->http,
            $this->factory,
            $this->tokenProvider(),
            new RequestBuilder($this->factory, $this->factory, $this->configuration),
            new ResponseInterpreter(),
            $this->rateLimiter,
            $this->ids,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    /**
     * The gateway built by the public factory, on top of the same mock client and fakes.
     */
    public function gateway(): UrssafHttpGateway
    {
        return $this->gatewayWith(GatewayOptions::defaults());
    }

    public function gatewayWith(GatewayOptions $options): UrssafHttpGateway
    {
        return UrssafGatewayFactory::createWith(
            new HttpTransport($this->http, $this->factory, $this->factory),
            $this->credentials,
            $this->tokens,
            $this->configuration,
            $options
                ->withRateLimiter($this->rateLimiter)
                ->withIdGenerator($this->ids)
                ->withClock($this->clock)
                ->withLogger($this->logger),
        );
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    /**
     * The requests sent to the API itself, without the token requests.
     *
     * @return list<RequestInterface>
     */
    public function apiRequests(): array
    {
        return array_values(array_filter(
            $this->requests(),
            static fn(RequestInterface $request): bool => $request->getUri()->getPath() !== '/api/oauth/v1/token',
        ));
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = array_pop($requests);

        return $last ?? throw new LogicException('No request was sent.');
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    public function respondToken(): void
    {
        $this->respond(200, json_encode([
            'access_token' => self::ISSUED,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));
    }

    public function fail(TransportFailure $exception): void
    {
        $this->http->addException($exception);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\IdGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

/**
 * Authenticated transport over PSR-18 (ADR 0002, ADR 0004). Build the gateway with
 * {@see \TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory}.
 *
 * Per attempt: rate limiter (a no-op unless configured), bearer token, optional request id header. A 401 invalidates
 * the cached token and the call is replayed exactly once, on every operation (a 401 proves nothing was processed);
 * there is no other retry. Statuses are then mapped by the {@see ResponseInterpreter}; on the two POST operations
 * every outcome that does not prove the call was not processed is an {@see OutcomeUnknown}.
 *
 * Neither the token, the query string, a header value, a request / response body nor the certificate content is
 * logged (ADR 0006).
 *
 * @internal
 */
final readonly class JsonApiClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private StreamFactoryInterface $streamFactory,
        private OAuth2TokenProvider $tokenProvider,
        private RequestBuilder $requestBuilder,
        private ResponseInterpreter $interpreter,
        private RateLimiter $rateLimiter,
        private IdGenerator $idGenerator,
        private Clock $clock,
        private ApiConfiguration $configuration,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Calls the API and decodes the JSON body of the declared success.
     *
     * @throws UrssafRequestRejected      HTTP 400 on every operation, 403 on the posting
     * @throws UrssafAuthenticationFailed HTTP 401 after the replay, 403 elsewhere
     * @throws UrssafResourceNotFound     HTTP 404 on the enrolment operations and the certificate
     * @throws UrssafUnavailable          HTTP 429, and 5xx or transport error on a read
     * @throws OutcomeUnknown             transport error, 5xx or unreadable / undeclared 2xx on a POST
     * @throws UnexpectedUrssafResponse   any other status, or an unreadable body of a read
     * @throws RateLimitExceeded          the local rate limiter refused the call
     */
    public function send(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ApiResponse
    {
        $response = $this->exchange($tenantId, $credentials, $request);

        try {
            return new ApiResponse($response->getStatusCode(), JsonDecoder::decode((string) $response->getBody()));
        } catch (UnreadablePayload $exception) {
            throw ApiResponse::unreadable($request->operation(), $response->getStatusCode(), $exception);
        }
    }

    /**
     * Calls the API and ignores the body of the declared success (the enrolment deletion). Same failures as
     * {@see self::send()}, minus the unreadable body.
     *
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafResourceNotFound
     * @throws UrssafUnavailable
     * @throws UnexpectedUrssafResponse
     * @throws RateLimitExceeded
     */
    public function acknowledge(TenantId $tenantId, Credentials $credentials, ApiRequest $request): void
    {
        $this->exchange($tenantId, $credentials, $request);
    }

    /**
     * Calls the API for a binary file: the file of the 200 answer, streamed and never decoded (ADR 0005), or null on
     * the 204 answer (dossier being instructed, no body read). Same failures as {@see self::acknowledge()}.
     *
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafResourceNotFound
     * @throws UrssafUnavailable
     * @throws UnexpectedUrssafResponse
     * @throws RateLimitExceeded
     */
    public function download(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ?DownloadedFile
    {
        $response = $this->exchange($tenantId, $credentials, $request);

        if ($response->getStatusCode() === 204) {
            return null;
        }

        return new DownloadedFile(
            new Psr7BinaryStream($response->getBody(), $this->streamFactory),
            $response->hasHeader('Content-Type') ? $response->getHeaderLine('Content-Type') : null,
            $response->hasHeader('Content-Disposition') ? $response->getHeaderLine('Content-Disposition') : null,
        );
    }

    private function exchange(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ResponseInterface
    {
        $response = $this->attempt($tenantId, $credentials, $request);

        if ($response->getStatusCode() === 401) {
            $this->tokenProvider->invalidate($credentials);
            $response = $this->attempt($tenantId, $credentials, $request);
        }

        return $this->interpreter->interpret($request, $response);
    }

    private function attempt(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ResponseInterface
    {
        $operation = $request->operation();
        $this->rateLimiter->acquire($tenantId, $operation);

        $context = ['method' => $request->method(), 'path' => $request->path()];
        $headers = [];
        $requestIdHeader = $this->configuration->requestIdHeader();
        if ($requestIdHeader !== null) {
            $requestId = $this->idGenerator->generate();
            $headers[$requestIdHeader] = $requestId;
            $context['request_id'] = $requestId;
        }

        $httpRequest = $this->requestBuilder->build(
            $request,
            $this->tokenProvider->accessToken($credentials, $operation),
            $headers,
        );
        $startedAt = $this->clock->now();

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('URSSAF call failed: transport error.', $context + [
                'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
                'exception' => $exception::class,
            ]);

            throw $operation->isUnsafeWrite()
                ? OutcomeUnknown::afterTransportError($operation, $exception::class)
                : UrssafUnavailable::dueToTransportError($operation, $exception::class);
        }

        $this->logger->info('URSSAF call.', $context + [
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
        ]);

        return $response;
    }
}

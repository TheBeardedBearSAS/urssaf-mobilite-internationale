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

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;

/**
 * Builds the PSR-7 request of an {@see ApiRequest} (ADR 0002): `Authorization: Bearer`, `Accept` (`application/pdf,
 * application/json` on a download), `User-Agent`, `Content-Type: application/json` only when there is a body, and the
 * optional request id header. The query string is RFC 3986.
 *
 * @internal
 */
final readonly class RequestBuilder
{
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ApiConfiguration $configuration,
    ) {}

    /**
     * @param array<string, string> $extraHeaders
     */
    public function build(ApiRequest $request, #[SensitiveParameter] string $token, array $extraHeaders): RequestInterface
    {
        $uri = $this->configuration->apiUri($request->path());
        if ($request->query() !== []) {
            $uri .= '?' . http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986);
        }

        $httpRequest = $this->requestFactory
            ->createRequest($request->method(), $uri)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', $request->isDownload() ? 'application/pdf, application/json' : 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent());

        foreach ($extraHeaders as $name => $value) {
            $httpRequest = $httpRequest->withHeader($name, $value);
        }

        $body = $request->jsonBody();
        if ($body !== null) {
            $httpRequest = $httpRequest
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        return $httpRequest;
    }
}

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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Immutable connection settings of the URSSAF gateway (ADR 0002, ADR 0007).
 *
 * Without override the API base URI is {@see Environment::baseUri()} + {@see UrssafEndpoints::API_BASE_PATH} and the
 * token URI {@see Environment::baseUri()} + {@see UrssafEndpoints::TOKEN_PATH}. An override replaces the whole URI
 * (host and path) and must be absolute, without user info, query or fragment, and use the `https` scheme: `http` is
 * refused, even for a local host, so that the client secret and the token never travel in clear (ADR 0002).
 */
final readonly class ApiConfiguration
{
    public const DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS = 60;
    public const DEFAULT_USER_AGENT = 'thebeardedbear-urssaf-mobilite-internationale';

    /** RFC 9110 "token" characters accepted for the optional request id header name. */
    private const HEADER_NAME_PATTERN = "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D";

    /**
     * @param string|null $baseUriOverride          replaces the API base URI, path included (e.g. a sandbox host or a proxy)
     * @param string|null $tokenUriOverride         replaces the OAuth2 token URI (the sandbox token host is not published)
     * @param string|null $requestIdHeader          name of the optional request id header, whose value is a UUID per
     *                                              attempt; none is sent when null (no header is published for this API)
     * @param int         $tokenSafetyMarginSeconds a cached token is considered expired this many seconds early
     *
     * @throws InvalidValue "base_uri.invalid_format", "base_uri.not_https", "token_uri.invalid_format",
     *                      "token_uri.not_https", "request_id_header.invalid_format" or "token_safety_margin.negative"
     */
    public function __construct(
        private Environment $environment,
        private ?string $baseUriOverride,
        private ?string $tokenUriOverride,
        private ?string $requestIdHeader,
        private int $tokenSafetyMarginSeconds = self::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
        private string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        if ($baseUriOverride !== null) {
            HttpsUri::assert('base_uri', $baseUriOverride);
        }
        if ($tokenUriOverride !== null) {
            HttpsUri::assert('token_uri', $tokenUriOverride);
        }
        if ($requestIdHeader !== null && preg_match(self::HEADER_NAME_PATTERN, $requestIdHeader) !== 1) {
            throw InvalidValue::invalidFormat('request_id_header', 'HTTP header name');
        }
        if ($tokenSafetyMarginSeconds < 0) {
            throw InvalidValue::negative('token_safety_margin');
        }
    }

    public static function forEnvironment(Environment $environment): self
    {
        return new self($environment, null, null, null);
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * Base URI of the API, without trailing slash (the paths of {@see UrssafEndpoints} are appended to it).
     */
    public function baseUri(): string
    {
        return rtrim($this->baseUriOverride ?? $this->environment->baseUri() . UrssafEndpoints::API_BASE_PATH, '/');
    }

    public function tokenUri(): string
    {
        return $this->tokenUriOverride ?? $this->environment->baseUri() . UrssafEndpoints::TOKEN_PATH;
    }

    /**
     * @param string $path one of the API paths of {@see UrssafEndpoints}
     */
    public function apiUri(string $path): string
    {
        return $this->baseUri() . $path;
    }

    public function requestIdHeader(): ?string
    {
        return $this->requestIdHeader;
    }

    public function tokenSafetyMarginSeconds(): int
    {
        return $this->tokenSafetyMarginSeconds;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }
}

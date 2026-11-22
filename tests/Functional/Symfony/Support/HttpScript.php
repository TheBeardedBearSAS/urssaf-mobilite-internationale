<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support;

use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;

/**
 * Scripted URSSAF platform behind the mocked PSR-18 client. Every route answers a synthetic success by default and can
 * be switched to a given status or to a transport failure. Requests are recorded per route.
 */
final class HttpScript
{
    public const ROUTE_AUTHENTICATION = 'authentication';
    public const ROUTE_ENROLMENT_REQUEST = 'enrolment_request';
    public const ROUTE_ENROLMENT_LIST = 'enrolment_list';
    public const ROUTE_ENROLMENT_GET = 'enrolment_get';
    public const ROUTE_ENROLMENT_DELETE = 'enrolment_delete';
    public const ROUTE_POSTING = 'posting';
    public const ROUTE_CERTIFICATE = 'certificate';

    /** A fake binary, not a PDF: nothing here is a real document. */
    public const CERTIFICATE_BYTES = "%PDF-1.7 synthetic certificate \x00\x01\x02 end";

    private const TOKEN_RESPONSE = '{"access_token":"fictitious-access-token","token_type":"Bearer","expires_in":3600}';

    /** @var array<string, RouteEndpoint> */
    private array $routes;

    public function __construct(private readonly Client $client)
    {
        $this->routes = [
            self::ROUTE_AUTHENTICATION => new RouteEndpoint(200, self::TOKEN_RESPONSE),
            self::ROUTE_ENROLMENT_REQUEST => new RouteEndpoint(201, Calls::ENROLMENT),
            self::ROUTE_ENROLMENT_LIST => new RouteEndpoint(200, '[' . Calls::ENROLMENT . ']'),
            self::ROUTE_ENROLMENT_GET => new RouteEndpoint(200, Calls::ENROLMENT),
            self::ROUTE_ENROLMENT_DELETE => new RouteEndpoint(200, ''),
            self::ROUTE_POSTING => new RouteEndpoint(200, '[{"id":"' . Calls::DOSSIER_ID . '"}]'),
            self::ROUTE_CERTIFICATE => new RouteEndpoint(200, self::CERTIFICATE_BYTES),
        ];
        $this->routes[self::ROUTE_CERTIFICATE]->respond(200, self::CERTIFICATE_BYTES, ['Content-Type' => 'application/pdf']);
        // Whatever the base path: it is overridable ("base_uri").
        $api = '';
        $matchers = [
            self::ROUTE_AUTHENTICATION => new RequestMatcher('/oauth(/v1)?/token$', null, ['POST']),
            self::ROUTE_ENROLMENT_REQUEST => new RequestMatcher($api . '/enrolements$', null, ['POST']),
            self::ROUTE_ENROLMENT_LIST => new RequestMatcher($api . '/enrolements$', null, ['GET']),
            self::ROUTE_ENROLMENT_GET => new RequestMatcher($api . '/enrolements/[0-9a-f-]{36}$', null, ['GET']),
            self::ROUTE_ENROLMENT_DELETE => new RequestMatcher($api . '/enrolements/[0-9a-f-]{36}$', null, ['DELETE']),
            self::ROUTE_POSTING => new RequestMatcher($api . '/declaration/detachement/UE$', null, ['POST']),
            self::ROUTE_CERTIFICATE => new RequestMatcher($api . '/certificat/[0-9a-f-]{36}$', null, ['GET']),
        ];
        foreach ($matchers as $route => $matcher) {
            $this->client->on($matcher, $this->routes[$route]);
        }
    }

    public function calls(string $route): int
    {
        return $this->routes[$route]->calls();
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(string $route, int $status, string $body, array $headers = []): void
    {
        $this->routes[$route]->respond($status, $body, $headers);
    }

    public function failTransport(string $route): void
    {
        $this->routes[$route]->failTransport();
    }
}

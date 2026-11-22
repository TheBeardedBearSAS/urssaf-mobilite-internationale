<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;

final class ApiConfigurationTest extends TestCase
{
    public function testSandboxDefaultsUseTheEnvironmentHostAndTheEndpointPaths(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertSame(Environment::Sandbox, $configuration->environment());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/enrolements',
            $configuration->apiUri('/enrolements'),
        );
    }

    public function testProductionDefaultsUseThePublicHost(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Production);

        static::assertSame('https://api.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
    }

    public function testDefaultsOfTheOtherSettings(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertNull($configuration->requestIdHeader());
        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('thebeardedbear-urssaf-mobilite-internationale', $configuration->userAgent());
    }

    public function testTheBaseUriOverrideReplacesTheWholeApiBaseUri(): void
    {
        $configuration = new ApiConfiguration(Environment::Production, 'https://mock.example.test:8443/api/', null, null);

        static::assertSame('https://mock.example.test:8443/api', $configuration->baseUri());
        static::assertSame('https://mock.example.test:8443/api/certificat/x', $configuration->apiUri('/certificat/x'));
        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
    }

    public function testTheTokenUriOverrideReplacesTheWholeTokenUri(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'https://token.example.test/oauth/token', null);

        static::assertSame('https://token.example.test/oauth/token', $configuration->tokenUri());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
    }

    public function testTheTokenUriOverrideKeepsItsTrailingSlash(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'https://token.example.test/oauth/', null);

        static::assertSame('https://token.example.test/oauth/', $configuration->tokenUri());
    }

    public function testTheSchemeIsCaseInsensitive(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, 'HTTPS://Mock.example.test/base', null, null);

        static::assertSame('HTTPS://Mock.example.test/base', $configuration->baseUri());
    }

    public function testOtherSettingsAreKept(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, null, 'X-Request-Id', 120, 'my-agent/1.0');

        static::assertSame('X-Request-Id', $configuration->requestIdHeader());
        static::assertSame(120, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('my-agent/1.0', $configuration->userAgent());
    }

    public function testAZeroSafetyMarginIsAccepted(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, null, null, 0);

        static::assertSame(0, $configuration->tokenSafetyMarginSeconds());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ApiConfigurationFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use ValueError;

#[CoversClass(ApiConfigurationFactory::class)]
final class ApiConfigurationFactoryTest extends TestCase
{
    public function testTheOptionsAreGivenToTheConfiguration(): void
    {
        $configuration = ApiConfigurationFactory::create(
            'production',
            'https://mock.example.test/mi/',
            'https://mock.example.test/token',
            'x-request-id',
            120,
            'host-app/1.0',
        );

        static::assertSame(Environment::Production, $configuration->environment());
        static::assertSame('https://mock.example.test/mi', $configuration->baseUri());
        static::assertSame('https://mock.example.test/token', $configuration->tokenUri());
        static::assertSame('x-request-id', $configuration->requestIdHeader());
        static::assertSame(120, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('host-app/1.0', $configuration->userAgent());
    }

    public function testNullAndTheEmptyStringAreNotConfigured(): void
    {
        foreach ([null, ''] as $blank) {
            $configuration = ApiConfigurationFactory::create('sandbox', $blank, $blank, $blank, 60, 'agent');

            static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
            static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
            static::assertNull($configuration->requestIdHeader());
        }
    }

    public function testEachOptionIsBlankedOnItsOwn(): void
    {
        $baseOnly = ApiConfigurationFactory::create('sandbox', 'https://mock.example.test/mi', '', '', 60, 'agent');
        $tokenOnly = ApiConfigurationFactory::create('sandbox', '', 'https://mock.example.test/token', '', 60, 'agent');
        $headerOnly = ApiConfigurationFactory::create('sandbox', '', '', 'x-request-id', 60, 'agent');

        static::assertSame('https://mock.example.test/mi', $baseOnly->baseUri());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $baseOnly->tokenUri());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $tokenOnly->baseUri());
        static::assertSame('https://mock.example.test/token', $tokenOnly->tokenUri());
        static::assertSame('x-request-id', $headerOnly->requestIdHeader());
        static::assertNull($baseOnly->requestIdHeader());
    }

    public function testAnInsecureUriIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('base_uri');

        ApiConfigurationFactory::create('sandbox', 'http://mock.example.test', null, null, 60, 'agent');
    }

    public function testAnInsecureTokenUriIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('token_uri');

        ApiConfigurationFactory::create('sandbox', null, 'http://mock.example.test/token', null, 60, 'agent');
    }

    public function testAnUnknownEnvironmentIsRefused(): void
    {
        $this->expectException(ValueError::class);

        ApiConfigurationFactory::create('staging', null, null, null, 60, 'agent');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use ValueError;

/**
 * @mago-expect lint:too-many-methods One test per option of the configuration tree.
 */
final class BundleConfigurationTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $harness = Harness::boot();

        $configuration = self::configuration($harness);
        static::assertSame(Environment::Sandbox, $configuration->environment());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
        static::assertNull($configuration->requestIdHeader());
        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $configuration->userAgent());
    }

    public function testTheProductionEnvironmentAndTheOverridesAreApplied(): void
    {
        $harness = Harness::boot([
            'environment' => 'production',
            'base_uri' => 'https://proxy.example.test/mi/',
            'token_uri' => 'https://auth.example.test/oauth/token',
            'user_agent' => 'host-app/1.0',
            'headers' => ['request_id' => 'x-request-id'],
            'token_safety_margin' => 120,
        ]);

        $configuration = self::configuration($harness);
        static::assertSame(Environment::Production, $configuration->environment());
        static::assertSame('https://proxy.example.test/mi', $configuration->baseUri());
        static::assertSame('https://auth.example.test/oauth/token', $configuration->tokenUri());
        static::assertSame('x-request-id', $configuration->requestIdHeader());
        static::assertSame('host-app/1.0', $configuration->userAgent());
        static::assertSame(120, $configuration->tokenSafetyMarginSeconds());
    }

    public function testTheSafetyMarginCanBeZero(): void
    {
        static::assertSame(0, self::configuration(Harness::boot(['token_safety_margin' => 0]))->tokenSafetyMarginSeconds());
    }

    public function testTheEnvironmentAndTheUrisCanBeEnvironmentVariables(): void
    {
        self::withEnvironmentVariables(
            ['URSSAF_TEST_ENV' => 'production', 'URSSAF_TEST_BASE_URI' => 'https://mock.example.test/mi', 'URSSAF_TEST_TOKEN_URI' => 'https://mock.example.test/token'],
            static function (): void {
                $harness = Harness::boot([
                    'environment' => '%env(URSSAF_TEST_ENV)%',
                    'base_uri' => '%env(URSSAF_TEST_BASE_URI)%',
                    'token_uri' => '%env(URSSAF_TEST_TOKEN_URI)%',
                ]);

                $configuration = self::configuration($harness);
                static::assertSame(Environment::Production, $configuration->environment());
                static::assertSame('https://mock.example.test/mi', $configuration->baseUri());
                static::assertSame('https://mock.example.test/token', $configuration->tokenUri());
            },
        );
    }

    public function testEmptyUriVariablesMeanNoOverride(): void
    {
        self::withEnvironmentVariables(
            ['URSSAF_TEST_BASE_URI' => '', 'URSSAF_TEST_TOKEN_URI' => ''],
            static function (): void {
                $harness = Harness::boot(['base_uri' => '%env(URSSAF_TEST_BASE_URI)%', 'token_uri' => '%env(URSSAF_TEST_TOKEN_URI)%']);

                $configuration = self::configuration($harness);
                static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', $configuration->baseUri());
                static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
            },
        );
    }

    public function testTheDefaultProcessorMakesAnUndefinedVariableNoOverride(): void
    {
        $harness = Harness::boot(['base_uri' => '%env(default::URSSAF_TEST_UNDEFINED_URI)%', 'token_uri' => '%env(default::URSSAF_TEST_UNDEFINED_URI)%']);

        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite', self::configuration($harness)->baseUri());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', self::configuration($harness)->tokenUri());
    }

    public function testAnInsecureUriFromAnEnvironmentVariableFailsAtRunTime(): void
    {
        self::withEnvironmentVariables(['URSSAF_TEST_BASE_URI' => 'http://mock.example.test'], static function (): void {
            $harness = Harness::boot(['base_uri' => '%env(URSSAF_TEST_BASE_URI)%']);

            try {
                self::configuration($harness);
                static::fail('The insecure URI must be refused.');
            } catch (InvalidValue $refused) {
                static::assertSame('base_uri.not_https', $refused->violationCode());
            }
        });
    }

    public function testAnUnknownEnvironmentFromAnEnvironmentVariableFailsAtRunTime(): void
    {
        self::withEnvironmentVariables(['URSSAF_TEST_ENV' => 'staging'], function (): void {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENV)%']);

            $this->expectException(ValueError::class);

            self::configuration($harness);
        });
    }

    public function testTheBundleExposesItsAliasAndDirectory(): void
    {
        $bundle = new UrssafMobiliteInternationaleBundle();

        static::assertSame('urssaf_mobilite_internationale', $bundle->getContainerExtension()?->getAlias());
        static::assertSame(\dirname(__DIR__, 3) . '/src/Bridge/Symfony', $bundle->getPath());
    }

    /**
     * @param array<string, string> $variables
     * @param callable(): void      $test
     * @param-immediately-invoked-callable $test
     */
    private static function withEnvironmentVariables(array $variables, callable $test): void
    {
        foreach ($variables as $name => $value) {
            $_ENV[$name] = $value;
        }
        try {
            $test();
        } finally {
            foreach (array_keys($variables) as $name) {
                unset($_ENV[$name]);
            }
        }
    }

    private static function configuration(Harness $harness): ApiConfiguration
    {
        return $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
    }
}

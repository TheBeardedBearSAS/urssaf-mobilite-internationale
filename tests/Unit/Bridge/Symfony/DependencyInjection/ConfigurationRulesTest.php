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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ConfigurationRules;

/**
 * @mago-expect lint:too-many-methods One data provider and one test per rule.
 */
#[CoversClass(ConfigurationRules::class)]
final class ConfigurationRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, bool, bool}> credential sources, expected "both", expected "none"
     */
    public static function credentialSources(): iterable
    {
        $credentials = ['tenant-1' => ['client_id' => 'client-1']];

        yield 'credentials only' => [['credentials' => $credentials, 'credentials_provider' => null], false, false];
        yield 'provider only' => [['credentials' => [], 'credentials_provider' => 'app.provider'], false, false];
        yield 'both' => [['credentials' => $credentials, 'credentials_provider' => 'app.provider'], true, false];
        yield 'neither' => [['credentials' => [], 'credentials_provider' => null], false, true];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('credentialSources')]
    public function testItDetectsTheCredentialSources(array $config, bool $both, bool $none): void
    {
        static::assertSame($both, ConfigurationRules::hasBothCredentialSources($config));
        static::assertSame($none, ConfigurationRules::hasNoCredentialSource($config));
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function gateways(): iterable
    {
        yield 'simulated in production' => ['production', true, true];
        yield 'simulated in sandbox' => ['sandbox', true, false];
        yield 'real gateway in production' => ['production', false, false];
        yield 'real gateway in sandbox' => ['sandbox', false, false];
    }

    #[DataProvider('gateways')]
    public function testTheSimulatedGatewayIsRefusedInProductionOnly(string $environment, bool $simulated, bool $refused): void
    {
        static::assertSame(
            $refused,
            ConfigurationRules::simulatedGatewayInProduction(['environment' => $environment, 'gateway' => ['simulated' => $simulated]]),
        );
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function environments(): iterable
    {
        yield 'sandbox' => ['sandbox', false];
        yield 'production' => ['production', false];
        yield 'placeholder dummy value' => ['', false];
        yield 'unknown name' => ['staging', true];
        yield 'wrong case' => ['Production', true];
        yield 'not a string' => [null, true];
    }

    #[DataProvider('environments')]
    public function testOnlyKnownEnvironmentsAndThePlaceholderValueAreAccepted(mixed $environment, bool $unknown): void
    {
        static::assertSame($unknown, ConfigurationRules::isUnknownEnvironment($environment));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function uris(): iterable
    {
        yield 'not configured' => [null, false];
        yield 'empty variable or placeholder dummy value' => ['', false];
        yield 'https host' => ['https://mock.example.test', false];
        yield 'https with a path' => ['https://mock.example.test/mi/', false];
        yield 'https in upper case' => ['HTTPS://mock.example.test', false];
        yield 'plain http' => ['http://mock.example.test', true];
        yield 'another scheme' => ['ftp://mock.example.test', true];
        yield 'relative' => ['/tiersdecl/v1/mobilite', true];
        yield 'user info' => ['https://user:pass@mock.example.test', true];
        yield 'query' => ['https://mock.example.test/mi?a=b', true];
        yield 'fragment' => ['https://mock.example.test/mi#a', true];
        yield 'blank' => [' ', true];
        yield 'not a string' => [42, true];
        yield 'a list' => [['https://mock.example.test'], true];
    }

    #[DataProvider('uris')]
    public function testOnlyAnAbsoluteHttpsUriIsAcceptedAsALiteral(mixed $uri, bool $invalid): void
    {
        static::assertSame($invalid, ConfigurationRules::isInvalidLiteralUri($uri));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, bool}>
     */
    public static function credentials(): iterable
    {
        yield 'environment variable as written' => [self::withSecret('%env(URSSAF_MI_CLIENT_SECRET)%'), false];
        yield 'environment variable as a placeholder' => [self::withSecret('env_URSSAF_MI_CLIENT_SECRET_0a1b2c'), false];
        yield 'secret with a processor' => [self::withSecret('%env(file:URSSAF_SECRET_FILE)%'), false];
        yield 'literal secret' => [self::withSecret('a-literal-secret'), true];
        yield 'literal secret that mentions env' => [self::withSecret('my_env_secret'), true];
        yield 'empty secret' => [self::withSecret(''), true];
        yield 'null secret' => [self::withSecret(null), true];
        yield 'numeric secret' => [self::withSecret(1234), true];
        yield 'missing secret' => [['client_id' => 'x'], true];
    }

    /**
     * The credentials of one tenant, with the given value as the client secret (fictitious, never a real one).
     *
     * @return array<string, mixed>
     */
    private static function withSecret(mixed $value): array
    {
        return ['client_id' => 'x', 'client_secret' => $value];
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    #[DataProvider('credentials')]
    public function testASecretMustBeAnEnvironmentVariable(array $credentials, bool $literal): void
    {
        static::assertSame($literal, ConfigurationRules::hasLiteralSecret($credentials));
    }
}

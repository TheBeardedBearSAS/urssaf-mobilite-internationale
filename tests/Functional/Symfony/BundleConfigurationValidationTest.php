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

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;

final class BundleConfigurationValidationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsAnInvalidConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot($config);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'unknown environment' => [['environment' => 'staging'], 'Unknown environment "staging": use "sandbox" or "production".'];
        yield 'negative safety margin' => [['token_safety_margin' => -1], 'token_safety_margin'];
        yield 'timeout too small' => [['http_timeout' => 0.0], 'http_timeout'];
        yield 'empty user agent' => [['user_agent' => ''], 'user_agent'];
        yield 'credentials and provider' => [['credentials_provider' => 'test.credentials_provider'], '"credentials" and "credentials_provider" are mutually exclusive.'];
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        yield 'empty client id' => [['credentials' => ['tenant-1' => ['client_id' => '', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]], 'client_id'];
        yield 'empty client secret' => [['credentials' => ['tenant-1' => ['client_id' => 'x', 'client_secret' => '']]], 'client_secret'];
        yield 'missing client secret' => [['credentials' => ['tenant-1' => ['client_id' => 'x', 'client_secret' => null]]], 'client_secret'];
        // @mago-expect lint:no-literal-password A literal secret on purpose: the configuration must refuse it.
        yield 'secret written in the configuration' => [
            ['credentials' => ['tenant-1' => ['client_id' => 'x', 'client_secret' => 'a-literal-secret']]],
            'The client secret must be an "%env(...)%" placeholder, never written in the configuration.',
        ];
        yield 'empty cache pool' => [['token_store' => ['cache_pool' => '']], 'token_store.cache_pool'];
        yield 'empty tenant' => [['tenants' => ['']], 'tenants'];
        yield 'plain http base uri' => [['base_uri' => 'http://mock.example.test'], 'Invalid base_uri: it must be an absolute https URI without user info, query or fragment.'];
        yield 'base uri with user info' => [['base_uri' => 'https://user:pass@mock.example.test'], 'Invalid base_uri'];
        yield 'relative base uri' => [['base_uri' => '/tiersdecl/v1/mobilite'], 'Invalid base_uri'];
        yield 'base uri that is not a string' => [['base_uri' => 42], 'base_uri'];
        yield 'plain http token uri' => [['token_uri' => 'http://mock.example.test/token'], 'Invalid token_uri: it must be an absolute https URI without user info, query or fragment.'];
        yield 'token uri with a query' => [['token_uri' => 'https://mock.example.test/token?a=b'], 'Invalid token_uri'];
        yield 'simulated gateway in production' => [
            ['environment' => 'production', 'gateway' => ['simulated' => true]],
            '"gateway.simulated" is meant for tests and cannot be used with "environment: production".',
        ];
        yield 'rate limiter that is not a boolean' => [['rate_limiter' => ['enabled' => 'yes']], 'rate_limiter.enabled'];
    }

    public function testTheEnvironmentIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('environment');

        (new TestKernel(['environment' => null]))->boot();
    }

    public function testSomeCredentialsSourceIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Configure either "credentials" or "credentials_provider".');

        Harness::boot(['credentials' => null]);
    }

    public function testAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('messenger');

        Harness::boot(['messenger' => ['enabled' => true]]);
    }

    public function testTheSimulatedGatewayIsAllowedInSandbox(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);

        static::assertTrue($harness->has(RequestEnrolmentHandler::class));
    }

    public function testTheCredentialsProviderAloneIsAccepted(): void
    {
        $harness = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials_provider']);

        static::assertTrue($harness->has(RequestEnrolmentHandler::class));
    }

    public function testTheSimulatedGatewayIsRefusedWhenTheEnvironmentResolvesToProduction(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            // The placeholder is not a literal "production": the build-time rule cannot see it, the container factory does.
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('The simulated gateway is meant for tests and cannot be used with the production environment.');

            $harness->get(RequestEnrolmentHandler::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT']);
        }
    }

    public function testTheSimulatedGatewayIsAvailableWhenTheEnvironmentResolvesToSandbox(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'sandbox';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            static::assertInstanceOf(SimulatedMobilityGateway::class, $harness->privateService(MobilityGateway::class, MobilityGateway::class));
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT']);
        }
    }
}

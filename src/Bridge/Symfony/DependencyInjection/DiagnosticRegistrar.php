<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\OAuth2TokenProvider;

/**
 * The "urssaf:mi:check" command and its checks, in report order (registration order, host checks afterwards unless
 * their tag has a priority above 1).
 *
 * The configuration and the token provider reach their checks through closures, so that an invalid configuration is
 * reported by the "configuration" check instead of preventing the command from running.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class DiagnosticRegistrar
{
    private const SHARED_POOL = 'cache.app';
    /** Above the default priority of the checks of the host (0), so that they run after those of the package. */
    private const CHECK_PRIORITY = 1;

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->check(ConfigurationCheck::class, [new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))]);
        $this->check(CredentialsCheck::class, [new Reference(CredentialsProvider::class)]);
        $this->registerGatewayCheck($config);
        $this->check(TokenStoreCheck::class, [
            new Reference(TokenStore::class),
            new Reference(Clock::class),
            $config['token_store']['service'] === null && $config['token_store']['cache_pool'] === self::SHARED_POOL,
        ]);

        $this->services->set(CheckInstallationCommand::class)
            ->args([
                new TaggedIteratorArgument(InstallationCheck::TAG),
                '%' . UrssafMobiliteInternationaleBundle::PARAMETER_TENANTS . '%',
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('console.command');
    }

    /**
     * @param Config $config
     */
    private function registerGatewayCheck(array $config): void
    {
        if ($config['gateway']['simulated']) {
            $this->check(SimulatedGatewayCheck::class, []);

            return;
        }
        $this->registerProbeTokenProvider($config['http_client']);
        $this->check(AuthenticationCheck::class, [
            new Reference(CredentialsProvider::class),
            new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER)),
        ]);
    }

    /**
     * The OAuth2 token provider of the authentication check: an in-memory token store of its own, so that every check
     * asks URSSAF whatever the token store of the application.
     */
    private function registerProbeTokenProvider(?string $httpClient): void
    {
        $factories = new Reference(ServiceIds::PSR18_CLIENT);
        $this->services->set(ServiceIds::PROBE_TOKEN_PROVIDER, OAuth2TokenProvider::class)
            ->args([
                new Reference($httpClient ?? ServiceIds::PSR18_CLIENT),
                $factories,
                $factories,
                new Definition(InMemoryTokenStore::class),
                new Reference(Clock::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag(GatewayRegistrar::LOGGER_TAG, ['channel' => GatewayRegistrar::LOGGER_CHANNEL]);
    }

    /**
     * @param class-string<InstallationCheck> $class
     * @param list<mixed>                     $arguments
     */
    private function check(string $class, array $arguments): void
    {
        $this->services->set($class)->args($arguments)->tag(InstallationCheck::TAG, ['priority' => self::CHECK_PRIORITY]);
    }
}

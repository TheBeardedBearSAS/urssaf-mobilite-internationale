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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\Psr6TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;

/**
 * Generic infrastructure: clock, API configuration, credentials, access token cache, posting validation policy.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class InfrastructureRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(Clock::class, Clock::class)
            ->factory(ClockFactory::class . '::create')
            ->args([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        // Resolved at runtime: the environment and the URIs are usually "%env()%" placeholders.
        $this->services->set(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)
            ->factory(ApiConfigurationFactory::class . '::create')
            ->args([
                $config['environment'],
                $config['base_uri'],
                $config['token_uri'],
                $config['headers']['request_id'],
                $config['token_safety_margin'],
                $config['user_agent'],
            ]);
        $this->services->set(PostingValidationPolicy::class)
            ->factory(PostingValidationPolicyFactory::class . '::create')
            ->args([$config['validation']['pdf_telework_rules']]);
        $this->registerCredentials($config['credentials_provider'], $config['credentials']);
        $this->registerTokenStore($config['token_store']);
    }

    /**
     * @param array<string, array{client_id: string, client_secret: string}> $credentials
     */
    private function registerCredentials(?string $provider, array $credentials): void
    {
        if ($provider !== null) {
            $this->services->alias(CredentialsProvider::class, $provider);

            return;
        }
        $definitions = [];
        foreach ($credentials as $tenantId => $tenant) {
            $definitions[$tenantId] = (new Definition(Credentials::class, [$tenant['client_id'], $tenant['client_secret']]))
                ->setFactory([Credentials::class, 'of']);
        }
        $this->services->set(CredentialsProvider::class, InMemoryCredentialsProvider::class)->args([$definitions]);
    }

    /**
     * @param array{cache_pool: string, service: ?string} $tokenStore
     */
    private function registerTokenStore(array $tokenStore): void
    {
        if ($tokenStore['service'] !== null) {
            $this->services->alias(TokenStore::class, $tokenStore['service']);

            return;
        }
        $this->services->set(TokenStore::class, Psr6TokenStore::class)->args([new Reference($tokenStore['cache_pool'])]);
    }
}

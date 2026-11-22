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
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;

/**
 * The URSSAF HTTP gateway and everything it needs: PSR-18 client and PSR-17 factories. With "gateway.simulated" the
 * in-memory simulator replaces all of it.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class GatewayRegistrar
{
    public const LOGGER_TAG = 'monolog.logger';
    public const LOGGER_CHANNEL = 'urssaf_mi';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        if ($config['gateway']['simulated']) {
            // The environment is resolved at runtime: the factory refuses "production" (the build-time rule only sees a literal).
            $this->services->set(MobilityGateway::class, SimulatedMobilityGateway::class)
                ->factory(SimulatedGatewayFactory::class . '::create')
                ->args([$config['environment']]);
            $this->services->alias(SimulatedMobilityGateway::class, MobilityGateway::class)->public();

            return;
        }
        $this->services->set(ServiceIds::SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory(HttpClient::class . '::create')
            // No redirect is ever followed: a replayed POST would carry the declaration (NIR) and the secret to another URL (ADR 0002).
            ->args([['timeout' => $config['http_timeout'], 'max_redirects' => 0]]);
        // Also provides the PSR-17 request and stream factories, whatever the PSR-18 client in use.
        $this->services->set(ServiceIds::PSR18_CLIENT, Psr18Client::class)->args([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)]);
        $this->services->set(ServiceIds::HTTP_TRANSPORT, HttpTransport::class)->args([
            new Reference($config['http_client'] ?? ServiceIds::PSR18_CLIENT),
            new Reference(ServiceIds::PSR18_CLIENT),
            new Reference(ServiceIds::PSR18_CLIENT),
        ]);
        $this->services->set(ServiceIds::GATEWAY_OPTIONS, GatewayOptions::class)
            ->factory(GatewayOptionsFactory::class . '::create')
            ->args([
                new Reference(Clock::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['rate_limiter']['enabled'],
                $config['certificate']['bad_request_as_in_progress'],
            ])
            // The logger is an argument of these options, not of the gateway: the channel tag must sit on this definition.
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        $this->services->set(MobilityGateway::class, UrssafHttpGateway::class)
            ->factory(UrssafGatewayFactory::class . '::createWith')
            ->args([
                new Reference(ServiceIds::HTTP_TRANSPORT),
                new Reference(CredentialsProvider::class),
                new Reference(TokenStore::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                new Reference(ServiceIds::GATEWAY_OPTIONS),
            ]);
    }
}

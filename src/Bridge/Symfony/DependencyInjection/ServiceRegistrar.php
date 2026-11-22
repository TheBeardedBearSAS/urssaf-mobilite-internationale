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

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * Declares the services of the bundle from its processed configuration. Services are private (the default of the
 * container configurator), except the use case handlers (the entry points of the host application) and the simulated
 * gateway (tests only).
 *
 * @phpstan-type Config array{
 *     environment: string,
 *     base_uri: ?string,
 *     token_uri: ?string,
 *     user_agent: string,
 *     headers: array{request_id: ?string},
 *     token_safety_margin: int,
 *     http_client: ?string,
 *     http_timeout: float,
 *     credentials_provider: ?string,
 *     credentials: array<string, array{client_id: string, client_secret: string}>,
 *     tenants: list<string>,
 *     token_store: array{cache_pool: string, service: ?string},
 *     gateway: array{simulated: bool},
 *     certificate: array{bad_request_as_in_progress: bool},
 *     validation: array{pdf_telework_rules: bool},
 *     rate_limiter: array{enabled: bool}
 * }
 */
final readonly class ServiceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        (new InfrastructureRegistrar($this->services))->register($config);
        (new GatewayRegistrar($this->services))->register($config);
        (new ApplicationRegistrar($this->services))->register();
        (new EntryPointRegistrar($this->services))->registerConsole();
        (new DiagnosticRegistrar($this->services))->register($config);
    }
}

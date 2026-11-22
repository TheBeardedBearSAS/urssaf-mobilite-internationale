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

/**
 * Ids of the private services of the bundle that have no interface to be identified by. Every id is prefixed
 * "urssaf_mi." so that the bundle can live next to the other URSSAF bundles (ADR 0010).
 */
final class ServiceIds
{
    public const SYMFONY_HTTP_CLIENT = 'urssaf_mi.http_client';
    public const PSR18_CLIENT = 'urssaf_mi.psr18_client';
    public const API_CONFIGURATION = 'urssaf_mi.api_configuration';
    public const GATEWAY_OPTIONS = 'urssaf_mi.gateway_options';
    public const HTTP_TRANSPORT = 'urssaf_mi.http_transport';
    /** Token provider of the "urssaf:mi:check" command: its own in-memory store, so that every check asks URSSAF. */
    public const PROBE_TOKEN_PROVIDER = 'urssaf_mi.probe_token_provider';
}

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

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;

/**
 * Configuration tree of the "urssaf_mobilite_internationale" bundle configuration (docs/symfony.md).
 */
final class BundleConfiguration
{
    public static function define(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->validate()
                ->ifTrue(ConfigurationRules::hasBothCredentialSources(...))
                ->thenInvalid('"credentials" and "credentials_provider" are mutually exclusive.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::hasNoCredentialSource(...))
                ->thenInvalid('Configure either "credentials" or "credentials_provider".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::simulatedGatewayInProduction(...))
                ->thenInvalid('"gateway.simulated" is meant for tests and cannot be used with "environment: production".')
            ->end()
            ->children()
                ->scalarNode('environment')
                    ->isRequired()
                    ->info('"sandbox" or "production"; usually "%env(URSSAF_MI_ENV)%" (resolved at runtime).')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isUnknownEnvironment(...))
                        ->thenInvalid('Unknown environment %s: use "sandbox" or "production".')
                    ->end()
                ->end()
                ->scalarNode('base_uri')
                    ->defaultNull()
                    ->info('Overrides the API base URI of the environment (https only); usually "%env(default::URSSAF_MI_BASE_URI)%" (empty: not overridden).')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isInvalidLiteralUri(...))
                        ->thenInvalid('Invalid base_uri: it must be an absolute https URI without user info, query or fragment.')
                    ->end()
                ->end()
                ->scalarNode('token_uri')
                    ->defaultNull()
                    ->info('Overrides the OAuth2 token URI of the environment (https only; the sandbox token host is not published); usually "%env(default::URSSAF_MI_TOKEN_URI)%".')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isInvalidLiteralUri(...))
                        ->thenInvalid('Invalid token_uri: it must be an absolute https URI without user info, query or fragment.')
                    ->end()
                ->end()
                ->scalarNode('user_agent')->defaultValue(ApiConfiguration::DEFAULT_USER_AGENT)->cannotBeEmpty()->info('Value of the User-Agent header.')->end()
                ->arrayNode('headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('request_id')->defaultNull()->info('Name of the optional request id header (its value is a UUID per attempt); null: not sent (no header is published for this API).')->end()
                    ->end()
                ->end()
                ->integerNode('token_safety_margin')->min(0)->defaultValue(ApiConfiguration::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS)->info('Seconds before expiry from which an access token is renewed.')->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client (default: a dedicated Symfony HttpClient).')->end()
                ->floatNode('http_timeout')->min(0.1)->defaultValue(15.0)->info('Timeout in seconds of the default HTTP client.')->end()
                ->scalarNode('credentials_provider')->defaultNull()->info('Service id of a CredentialsProvider.')->end()
                ->arrayNode('credentials')
                    ->info('Credentials by tenant id; the client secret must be an "%env(...)%" placeholder.')
                    ->useAttributeAsKey('tenant_id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->validate()
                            ->ifTrue(ConfigurationRules::hasLiteralSecret(...))
                            ->thenInvalid('The client secret must be an "%%env(...)%%" placeholder, never written in the configuration.')
                        ->end()
                        ->children()
                            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tenants')
                    ->info('Tenants processed by commands that cannot discover them (default: the keys of "credentials").')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('token_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache_pool')->defaultValue('cache.app')->cannotBeEmpty()->info('Service id of a PSR-6 cache pool.')->end()
                        ->scalarNode('service')->defaultNull()->info('Service id of a TokenStore (takes precedence over the cache pool).')->end()
                    ->end()
                ->end()
                ->arrayNode('gateway')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('simulated')->defaultFalse()->info('Tests only: replaces the URSSAF API with the in-memory Testing\\SimulatedMobilityGateway (public service, to script the scenarios).')->end()
                    ->end()
                ->end()
                ->arrayNode('certificate')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('bad_request_as_in_progress')->defaultTrue()->info('A 400 on the certificate download means "not available yet" (a package choice, ADR 0003); false: it is a rejected request.')->end()
                    ->end()
                ->end()
                ->arrayNode('validation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('pdf_telework_rules')->defaultFalse()->info('Also applies the PDF telework rules before every posting (ADR 0008).')->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limiter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Enforces the published quota (200 requests per minute for the whole API) per tenant, all operations together, in the current process.')->end()
                    ->end()
                ->end()
            ->end();
    }
}

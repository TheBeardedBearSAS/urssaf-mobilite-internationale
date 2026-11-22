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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;

/**
 * Validation predicates of the configuration tree (each one is true when the value is invalid).
 *
 * An option that is an "%env()%" placeholder reaches these predicates as the empty string (or as a placeholder
 * string): it is accepted here and checked at run time, by {@see ApiConfigurationFactory} and by the "configuration"
 * check of "urssaf:mi:check".
 */
final class ConfigurationRules
{
    /**
     * A complete "%env(NAME)%" placeholder (processors allowed), or what the container makes of it while it validates the
     * configuration: "env_" + 16 hexadecimal characters, the name of the variable, 32 hexadecimal characters
     * (EnvPlaceholderParameterBag). Anchored on both sides: "env_…" or "%env(" inside a literal secret does not count.
     */
    private const ENV_PLACEHOLDER = '/^(?:%env\([^()%\s]+\)%|env_[0-9a-f]{16}_[A-Za-z0-9_]+_[0-9a-f]{32})$/D';

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasBothCredentialSources(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] !== [] && $config['credentials_provider'] !== null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasNoCredentialSource(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] === [] && $config['credentials_provider'] === null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function simulatedGatewayInProduction(array $value): bool
    {
        /** @var array{environment: string, gateway: array{simulated: bool}} $config */
        $config = $value;

        return $config['gateway']['simulated'] && $config['environment'] === Environment::Production->value;
    }

    /**
     * The empty string is the dummy value of an environment variable placeholder while the container is validated:
     * an empty variable fails at runtime instead (Environment::from()).
     */
    public static function isUnknownEnvironment(mixed $environment): bool
    {
        return !\in_array($environment, ['', ...array_map(static fn(Environment $case): string => $case->value, Environment::cases())], true);
    }

    /**
     * A literal base URI or token URI that ApiConfiguration refuses (not absolute, user info, query, fragment, or not
     * https). Null and the empty string are "not configured" (an empty environment variable included).
     */
    public static function isInvalidLiteralUri(mixed $uri): bool
    {
        if ($uri === null || $uri === '') {
            return false;
        }
        if (!\is_string($uri)) {
            return true;
        }
        try {
            new ApiConfiguration(Environment::Sandbox, $uri, $uri, null);
        } catch (InvalidValue) {
            return true;
        }

        return false;
    }

    /**
     * The client secret must come from an environment variable (or a secret), never be written in the configuration.
     * Checked on the credentials of a tenant, not on the secret alone: a node with a validation closure cannot hold an
     * environment variable (Symfony), and the parent receives the placeholder as written.
     *
     * @param array<array-key, mixed> $value
     */
    public static function hasLiteralSecret(array $value): bool
    {
        // @mago-expect analysis:mixed-assignment The configuration is untyped: the value is narrowed on the next line.
        $secret = $value['client_secret'] ?? null;

        return !\is_string($secret) || preg_match(self::ENV_PLACEHOLDER, $secret) !== 1;
    }
}

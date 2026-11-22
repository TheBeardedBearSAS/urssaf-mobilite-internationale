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
use ValueError;

/**
 * Container factory of the API configuration, from options that may be "%env()%" placeholders resolved at run time.
 *
 * An empty string is "not configured", like null: the recipe ships URSSAF_MI_BASE_URI and URSSAF_MI_TOKEN_URI empty,
 * and an empty environment variable resolves to the empty string.
 */
final class ApiConfigurationFactory
{
    /**
     * @throws ValueError   unknown environment
     * @throws InvalidValue an invalid (non-https...) URI, request id header or safety margin
     *
     * @mago-expect lint:excessive-parameter-list One argument per option of the configuration tree, given by the container.
     */
    public static function create(
        string $environment,
        ?string $baseUri,
        ?string $tokenUri,
        ?string $requestIdHeader,
        int $tokenSafetyMarginSeconds,
        string $userAgent,
    ): ApiConfiguration {
        return new ApiConfiguration(
            Environment::from($environment),
            self::blankToNull($baseUri),
            self::blankToNull($tokenUri),
            self::blankToNull($requestIdHeader),
            $tokenSafetyMarginSeconds,
            $userAgent,
        );
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}

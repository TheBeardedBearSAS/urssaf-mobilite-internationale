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

use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;

/**
 * Container factory of the gateway options: the clock of the package, the optional logger of the application, the
 * opt-in client-side rate limiter and the handling of a 400 on the certificate (ADR 0003).
 */
final class GatewayOptionsFactory
{
    /**
     * @mago-expect lint:no-boolean-flag-parameter The two switches are bundle options (rate_limiter.enabled, certificate.bad_request_as_in_progress), given by the container.
     */
    public static function create(
        Clock $clock,
        ?LoggerInterface $logger,
        bool $rateLimiterEnabled,
        bool $certificateBadRequestAsInProgress,
    ): GatewayOptions {
        $options = GatewayOptions::defaults()
            ->withClock($clock)
            ->withCertificateBadRequestAsInProgress($certificateBadRequestAsInProgress);
        if ($rateLimiterEnabled) {
            $options = $options->withRateLimiter(new SlidingWindowRateLimiter($clock));
        }

        return $logger instanceof LoggerInterface ? $options->withLogger($logger) : $options;
    }
}

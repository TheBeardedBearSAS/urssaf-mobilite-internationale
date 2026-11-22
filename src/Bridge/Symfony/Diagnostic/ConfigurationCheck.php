<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic;

use Closure;
use Override;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use ValueError;

/**
 * The options resolved at run time (environment, base URI, token URI, request id header, safety margin) are valid: an
 * invalid value is a failed check instead of a stack trace. The configuration is built by a closure, inside the check.
 * It reports the environment and the resolved URIs, never a credential.
 */
final readonly class ConfigurationCheck implements InstallationCheck
{
    private const NAME = 'configuration';

    /**
     * @param Closure(): ApiConfiguration $configuration builds the configuration (it may throw for an invalid option)
     */
    public function __construct(private Closure $configuration) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        // @mago-expect analysis:avoid-catching-error Environment::from() throws a ValueError for an unknown environment: it is reported by the check, not a crash.
        try {
            $configuration = ($this->configuration)();
        } catch (ValueError|InvalidValue $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, $failure->getMessage())];
        }

        return [new CheckResult(
            self::NAME,
            '',
            CheckStatus::Ok,
            \sprintf('%s: API %s, token %s', $configuration->environment()->value, $configuration->baseUri(), $configuration->tokenUri()),
        )];
    }
}

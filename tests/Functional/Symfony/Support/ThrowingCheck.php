<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support;

use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvariantViolation;

/**
 * A host check that raises a package failure for the tenant "tenant-throws" and reports nothing otherwise.
 */
final class ThrowingCheck implements InstallationCheck
{
    public const TENANT = 'tenant-throws';

    public function run(CheckContext $context): array
    {
        foreach ($context->tenants as $tenant) {
            if ($tenant->value() === self::TENANT) {
                throw InvariantViolation::because('host.check_broken', 'The host check is broken.');
            }
        }

        return [];
    }
}

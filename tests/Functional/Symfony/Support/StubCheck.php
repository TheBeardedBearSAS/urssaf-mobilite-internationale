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
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * A host check, autoconfigured by the bundle.
 */
final class StubCheck implements InstallationCheck
{
    public function run(CheckContext $context): array
    {
        return [new CheckResult('stub', '', CheckStatus::Ok, 'fine')];
    }
}

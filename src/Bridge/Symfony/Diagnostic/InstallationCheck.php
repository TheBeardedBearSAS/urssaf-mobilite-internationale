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

/**
 * A check run by `urssaf:mi:check`. Services tagged "urssaf_mi.installation_check" (autoconfigured) run after the
 * checks of the package, unless their tag has a priority of 1 or more: the checks of the package have a priority of 1,
 * so a tag priority above 1 puts the checks of the host before them.
 */
interface InstallationCheck
{
    public const TAG = 'urssaf_mi.installation_check';

    /**
     * @return list<CheckResult>
     */
    public function run(CheckContext $context): array;
}

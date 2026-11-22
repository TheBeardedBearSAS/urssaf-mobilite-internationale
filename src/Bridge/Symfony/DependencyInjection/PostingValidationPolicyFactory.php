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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;

/**
 * Container factory of the local validation policy applied before every posting (ADR 0008).
 */
final class PostingValidationPolicyFactory
{
    /**
     * @mago-expect lint:no-boolean-flag-parameter The flag is the validation.pdf_telework_rules bundle option, given by the container.
     */
    public static function create(bool $pdfTeleworkRules): PostingValidationPolicy
    {
        $policy = PostingValidationPolicy::default();

        return $pdfTeleworkRules ? $policy->withPdfTeleworkRules() : $policy;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestReader;

/**
 * Body of "POST /declaration/detachement/UE". "declarant" and "declaration" are optional in the Swagger and required
 * here (PDF, ADR 0008). Never persisted, logged nor serialized by the package (ADR 0006).
 */
final readonly class PostingRequest
{
    use PersonalData;

    private function __construct(private Declarant $declarant, private Declaration $declaration) {}

    /**
     * Typed path: the objects already enforce their own rules. The {@see PostingValidationPolicy} is applied again by
     * the submission handler.
     */
    public static function of(Declarant $declarant, Declaration $declaration): self
    {
        return new self($declarant, $declaration);
    }

    /**
     * Cumulative path: every violation of the draft and of the policy at once.
     *
     * @throws ValidationFailed with every violation found
     */
    public static function fromDraft(PostingRequestDraft $draft, PostingValidationPolicy $policy): self
    {
        return PostingRequestReader::read($draft, $policy)->toPostingRequest();
    }

    public function declarant(): Declarant
    {
        return $this->declarant;
    }

    public function declaration(): Declaration
    {
        return $this->declaration;
    }
}

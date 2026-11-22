<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PersonalData;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;

/**
 * A draft whose every required part was read; its result may still hold violations of optional parts or of the policy.
 */
final readonly class CompletePostingDraft implements PostingDraftReading
{
    use PersonalData;

    public function __construct(private ValidationResult $result, private PostingRequest $request) {}

    public function result(): ValidationResult
    {
        return $this->result;
    }

    public function toPostingRequest(): PostingRequest
    {
        $this->result->throwIfInvalid();

        return $this->request;
    }
}

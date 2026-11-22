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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;

/**
 * A draft of which a required part could not be read (its result therefore holds violations).
 */
final readonly class IncompletePostingDraft implements PostingDraftReading
{
    public function __construct(private ValidationResult $result) {}

    public function result(): ValidationResult
    {
        return $this->result;
    }

    public function toPostingRequest(): PostingRequest
    {
        throw ValidationFailed::withResult($this->result);
    }
}

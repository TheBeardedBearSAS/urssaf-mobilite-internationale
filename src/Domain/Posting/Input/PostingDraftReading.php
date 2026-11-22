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
 * Outcome of {@see PostingRequestReader::read()}: every violation found and, when possible, the request it describes.
 * Either {@see CompletePostingDraft} or {@see IncompletePostingDraft}.
 */
interface PostingDraftReading
{
    public function result(): ValidationResult;

    /**
     * @throws ValidationFailed when the draft is not valid
     */
    public function toPostingRequest(): PostingRequest;
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Benchmark;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input\PostingDrafts;

/**
 * Local validation of a complete posting request (worker, telework, host address, declared and current activities)
 * before it is encoded and sent, and the same reading of a draft that collects many violations.
 */
final readonly class PostingValidationBench
{
    private PostingRequestDraft $complete;

    private PostingRequestDraft $incomplete;

    private PostingValidationPolicy $policy;

    public function __construct()
    {
        $this->complete = PostingDrafts::request(PostingDrafts::details());
        $this->incomplete = PostingDrafts::request(PostingDrafts::emptyDetails());
        $this->policy = PostingValidationPolicy::default()->withPdfTeleworkRules();
    }

    public function benchReadACompleteDraft(): void
    {
        PostingRequestReader::read($this->complete, $this->policy);
    }

    public function benchBuildAPostingRequestFromACompleteDraft(): void
    {
        PostingRequest::fromDraft($this->complete, $this->policy);
    }

    public function benchReadAnIncompleteDraft(): void
    {
        PostingRequestReader::read($this->incomplete, $this->policy);
    }
}

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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\EnrolmentMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input\PostingDrafts;

/**
 * Encoding of the request bodies: a complete posting request (to the wire array, then to the JSON string) and an
 * enrolment request.
 */
final readonly class WireEncodingBench
{
    private const ENROLMENT_BODIES = 100;

    private PostingRequestEncoder $encoder;

    private EnrolmentMapper $enrolmentMapper;

    private PostingRequest $request;

    private Siren $siren;

    private Nic $nic;

    public function __construct()
    {
        $this->encoder = new PostingRequestEncoder();
        $this->enrolmentMapper = new EnrolmentMapper();
        $this->request = PostingRequest::fromDraft(
            PostingDrafts::request(PostingDrafts::details()),
            PostingValidationPolicy::default(),
        );
        $this->siren = Siren::fromString('404833048');
        $this->nic = Nic::fromString('00022');
    }

    public function benchEncodeAPostingRequest(): void
    {
        $this->encoder->encode($this->request);
    }

    public function benchEncodeAPostingRequestToJson(): void
    {
        JsonEncoder::encode($this->encoder->encode($this->request));
    }

    /**
     * A hundred bodies per call, so that the measure is far above the timer resolution.
     */
    public function benchEncodeEnrolmentRequests(): int
    {
        $encoded = 0;
        for ($body = 0; $body < self::ENROLMENT_BODIES; ++$body) {
            $encoded += \count($this->enrolmentMapper->toRequestBody($this->siren, $this->nic, EnrolmentCategory::Rg));
        }

        return $encoded;
    }
}

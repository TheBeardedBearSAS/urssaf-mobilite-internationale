<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Standalone;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPostingHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;

/**
 * The six use cases of the package wired together, for applications without the Symfony bundle; built by
 * {@see UrssafMobiliteInternationaleBuilder}. Each method returns a new handler (handlers are stateless).
 */
final readonly class UrssafMobiliteInternationale
{
    public function __construct(private MobilityGateway $gateway, private PostingValidationPolicy $policy) {}

    public function gateway(): MobilityGateway
    {
        return $this->gateway;
    }

    public function requestEnrolment(): RequestEnrolmentHandler
    {
        return new RequestEnrolmentHandler($this->gateway);
    }

    public function listEnrolments(): ListEnrolmentsHandler
    {
        return new ListEnrolmentsHandler($this->gateway);
    }

    public function getEnrolment(): GetEnrolmentHandler
    {
        return new GetEnrolmentHandler($this->gateway);
    }

    public function deleteEnrolment(): DeleteEnrolmentHandler
    {
        return new DeleteEnrolmentHandler($this->gateway);
    }

    public function submitPosting(): SubmitPostingHandler
    {
        return new SubmitPostingHandler($this->gateway, $this->policy);
    }

    public function getCertificate(): GetCertificateHandler
    {
        return new GetCertificateHandler($this->gateway);
    }
}

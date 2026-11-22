<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\IdGenerator;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimiter;

/**
 * Optional collaborators and switches of the gateway (see {@see UrssafGatewayFactory}), immutable; each one not given
 * keeps its default:
 * - rate limiter: none (opt-in, ADR 0000 row 14): the published quota is global to the API and informational; give a
 *   {@see \TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\RateLimiter\SlidingWindowRateLimiter} or a shared
 *   one when several workers call URSSAF;
 * - id generator: random UUIDs, used only when {@see ApiConfiguration::requestIdHeader()} is configured (no request id
 *   header is sent by default);
 * - clock: the system clock (UTC);
 * - logger: no logging;
 * - certificate 400 as "in progress": true (ADR 0003, a package choice, not a spec rule).
 *
 *     GatewayOptions::defaults()->withLogger($logger)->withCertificateBadRequestAsInProgress(false)
 */
final readonly class GatewayOptions
{
    private function __construct(
        private ?RateLimiter $rateLimiter,
        private ?IdGenerator $idGenerator,
        private ?Clock $clock,
        private ?LoggerInterface $logger,
        private bool $certificateBadRequestAsInProgress,
    ) {}

    public static function defaults(): self
    {
        return new self(null, null, null, null, true);
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return new self(
            $rateLimiter,
            $this->idGenerator,
            $this->clock,
            $this->logger,
            $this->certificateBadRequestAsInProgress,
        );
    }

    public function withIdGenerator(IdGenerator $idGenerator): self
    {
        return new self(
            $this->rateLimiter,
            $idGenerator,
            $this->clock,
            $this->logger,
            $this->certificateBadRequestAsInProgress,
        );
    }

    public function withClock(Clock $clock): self
    {
        return new self(
            $this->rateLimiter,
            $this->idGenerator,
            $clock,
            $this->logger,
            $this->certificateBadRequestAsInProgress,
        );
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self(
            $this->rateLimiter,
            $this->idGenerator,
            $this->clock,
            $logger,
            $this->certificateBadRequestAsInProgress,
        );
    }

    /**
     * Whether a 400 on `GET /certificat/{id}` is "in progress" (true, default) or an `UrssafRequestRejected` (false).
     */
    public function withCertificateBadRequestAsInProgress(bool $asInProgress): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $this->clock, $this->logger, $asInProgress);
    }

    /**
     * Null when not given (no client-side throttling).
     */
    public function rateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function idGenerator(): ?IdGenerator
    {
        return $this->idGenerator;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function clock(): ?Clock
    {
        return $this->clock;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function certificateBadRequestAsInProgress(): bool
    {
        return $this->certificateBadRequestAsInProgress;
    }
}

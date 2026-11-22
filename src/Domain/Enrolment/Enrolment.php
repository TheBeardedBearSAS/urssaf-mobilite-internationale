<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

/**
 * An enrolment as returned by URSSAF (ADR 0009). Only the identifier is required; every other field may be absent.
 *
 * "category" and "status" keep the raw wire string next to a nullable typed view: an unknown value never breaks a read.
 */
final readonly class Enrolment
{
    private function __construct(
        private EnrolmentId $id,
        private ?Siren $siren,
        private ?Nic $nic,
        private ?string $rawCategory,
        private ?string $rawStatus,
        private ?Instant $requestedAt,
        private ?Instant $updatedAt,
    ) {}

    /**
     * @mago-expect lint:excessive-parameter-list One argument per field of the contract schema, all of them needed to rebuild a read.
     */
    public static function of(
        EnrolmentId $id,
        ?Siren $siren,
        ?Nic $nic,
        ?string $rawCategory,
        ?string $rawStatus,
        ?Instant $requestedAt,
        ?Instant $updatedAt,
    ): self {
        return new self($id, $siren, $nic, $rawCategory, $rawStatus, $requestedAt, $updatedAt);
    }

    public function id(): EnrolmentId
    {
        return $this->id;
    }

    public function siren(): ?Siren
    {
        return $this->siren;
    }

    public function nic(): ?Nic
    {
        return $this->nic;
    }

    /**
     * Wire value of "categorie", verbatim.
     */
    public function rawCategory(): ?string
    {
        return $this->rawCategory;
    }

    /**
     * Null when absent or unknown to this version of the package.
     */
    public function category(): ?EnrolmentCategory
    {
        return $this->rawCategory === null ? null : EnrolmentCategory::fromCode($this->rawCategory);
    }

    /**
     * Wire value of "status", verbatim.
     */
    public function rawStatus(): ?string
    {
        return $this->rawStatus;
    }

    /**
     * Null when absent or unknown to this version of the package.
     */
    public function status(): ?EnrolmentStatus
    {
        return $this->rawStatus === null ? null : EnrolmentStatus::fromCode($this->rawStatus);
    }

    public function isAccepted(): bool
    {
        return $this->status() === EnrolmentStatus::Accepte;
    }

    public function requestedAt(): ?Instant
    {
        return $this->requestedAt;
    }

    public function updatedAt(): ?Instant
    {
        return $this->updatedAt;
    }
}

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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * One page of enrolments (ADR 0009). The API returns a bare array without total: the last page is the first one
 * shorter than the requested limit, and no total is invented.
 */
final readonly class EnrolmentPage
{
    /**
     * @param list<Enrolment> $enrolments
     */
    private function __construct(private array $enrolments, private int $offset, private int $limit) {}

    /**
     * @param list<Enrolment> $enrolments
     *
     * @throws InvalidValue "offset.negative" or "limit.not_positive"
     */
    public static function of(array $enrolments, int $offset, int $limit): self
    {
        if ($offset < 0) {
            throw InvalidValue::negative('offset');
        }
        if ($limit < 1) {
            throw InvalidValue::notPositive('limit');
        }

        return new self($enrolments, $offset, $limit);
    }

    /**
     * @return list<Enrolment>
     */
    public function enrolments(): array
    {
        return $this->enrolments;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function isLastPage(): bool
    {
        return \count($this->enrolments) < $this->limit;
    }

    /**
     * Offset of the next page, null on the last page.
     */
    public function nextOffset(): ?int
    {
        return $this->isLastPage() ? null : $this->offset + \count($this->enrolments);
    }
}

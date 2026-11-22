<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use RuntimeException;
use Throwable;

/**
 * An URSSAF body that does not match the contract. The gateway turns it into the exception that fits the operation
 * (`OutcomeUnknown::unreadableAnswer()` on the two POST, an unexpected-response failure on the reads).
 */
final class UnreadablePayload extends RuntimeException
{
    private function __construct(
        private readonly string $reason,
        ?Throwable $previous,
    ) {
        parent::__construct('Unreadable URSSAF payload: ' . $reason . '.', 0, $previous);
    }

    /**
     * @param string $reason short technical reason naming a field or a shape, never a payload value
     */
    public static function because(string $reason): self
    {
        return new self($reason, null);
    }

    /**
     * @param string $reason short technical reason, never a payload value
     */
    public static function causedBy(string $reason, Throwable $previous): self
    {
        return new self($reason, $previous);
    }

    /**
     * Short technical reason, safe to log and to pass to `OutcomeUnknown::unreadableAnswer()`.
     */
    public function reason(): string
    {
        return $this->reason;
    }
}

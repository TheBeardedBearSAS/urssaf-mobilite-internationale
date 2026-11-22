<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception;

use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use Throwable;

/**
 * URSSAF answered with something the contract does not describe on a call that was not processed or is safe to repeat
 * (unmapped HTTP status, unreadable or non-conforming body of a read, malformed token answer). Not retryable: the
 * answer must be investigated. The message names the operation, the status or a technical reason, never a body
 * (ADR 0006); the two POST operations throw {@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown}
 * instead when the call may have been processed (ADR 0004).
 */
final class UnexpectedUrssafResponse extends RuntimeException implements UrssafException
{
    /**
     * @param string $reason short technical reason, never a payload value
     */
    public static function invalidPayload(string $reason): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason));
    }

    /**
     * @param string $reason short technical reason, never a payload value
     */
    public static function causedBy(string $reason, Throwable $previous): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason), 0, $previous);
    }

    public static function unexpectedStatus(Operation $operation, int $httpStatus): self
    {
        return new self(
            \sprintf('Unexpected URSSAF response for operation %s: HTTP %d.', $operation->value, $httpStatus),
            $httpStatus,
        );
    }
}

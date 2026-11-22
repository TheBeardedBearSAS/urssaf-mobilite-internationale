<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

/**
 * Successful JSON answer of an API call: the declared HTTP status and the decoded body.
 *
 * @internal
 */
final readonly class ApiResponse
{
    public function __construct(private int $status, private Payload $payload) {}

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): Payload
    {
        return $this->payload;
    }

    /**
     * The failure for a declared success whose body does not match the contract (ADR 0004): an {@see OutcomeUnknown}
     * on the two POST operations (the call may have been processed), an {@see UnexpectedUrssafResponse} on the others.
     * It never carries a payload value.
     */
    public static function unreadable(Operation $operation, int $status, UnreadablePayload $exception): OutcomeUnknown|UnexpectedUrssafResponse
    {
        return $operation->isUnsafeWrite()
            ? OutcomeUnknown::unreadableAnswer($operation, $status, $exception->reason())
            : UnexpectedUrssafResponse::causedBy($exception->reason(), $exception);
    }
}

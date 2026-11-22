<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use Throwable;

/**
 * Expected failure of every non-success status of every operation (api-reference §2, ADR 0002, ADR 0004). A 401 is
 * replayed once, so the expectation is for a 401 that persists.
 */
final class StatusMatrix
{
    private function __construct() {}

    /**
     * @return iterable<string, array{Operation, int, class-string<Throwable>}>
     */
    public static function failures(): iterable
    {
        foreach (Operation::cases() as $operation) {
            foreach (self::expectations($operation) as $status => $class) {
                yield $operation->value . ' ' . $status => [$operation, $status, $class];
            }
        }
    }

    /**
     * @return array<int, class-string<Throwable>>
     */
    private static function expectations(Operation $operation): array
    {
        $posting = $operation === Operation::SubmitPosting;
        $serverError = $operation->isUnsafeWrite() ? OutcomeUnknown::class : UrssafUnavailable::class;

        return [
            400 => UrssafRequestRejected::class,
            401 => UrssafAuthenticationFailed::class,
            403 => $posting ? UrssafRequestRejected::class : UrssafAuthenticationFailed::class,
            404 => $posting ? UnexpectedUrssafResponse::class : UrssafResourceNotFound::class,
            405 => UnexpectedUrssafResponse::class,
            409 => UnexpectedUrssafResponse::class,
            418 => UnexpectedUrssafResponse::class,
            429 => UrssafUnavailable::class,
            499 => UnexpectedUrssafResponse::class,
            500 => $serverError,
            502 => $serverError,
            503 => $serverError,
            504 => $serverError,
            599 => $serverError,
            100 => UnexpectedUrssafResponse::class,
            199 => UnexpectedUrssafResponse::class,
            300 => UnexpectedUrssafResponse::class,
            302 => UnexpectedUrssafResponse::class,
            304 => UnexpectedUrssafResponse::class,
            399 => UnexpectedUrssafResponse::class,
            600 => UnexpectedUrssafResponse::class,
        ];
    }
}

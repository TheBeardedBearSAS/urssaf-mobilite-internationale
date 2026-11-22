<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

/**
 * One of the two POST operations may have been processed by URSSAF but its answer was lost, unexpected or unreadable
 * (ADR 0004): transport error, any 5xx, any 2xx other than the declared success with a readable body. Never resent
 * automatically and not retryable:
 * - enrolment: list the enrolments and look for the SIREN (and NIC) before resending;
 * - posting: do not resend before checking with URSSAF outside the API (case manager, urssaf.fr).
 *
 * The message never contains the payload nor the transport message (it embeds the URI): only the class name of the
 * cause is kept.
 */
final class OutcomeUnknown extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ?string $causeClass,
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }

    /**
     * @param string $causeClass class name of the transport exception (its message is never kept)
     */
    public static function afterTransportError(Operation $operation, string $causeClass): self
    {
        return new self(
            \sprintf('URSSAF operation %s failed with %s: %s', $operation->value, $causeClass, self::advice($operation)),
            $operation,
            null,
            $causeClass,
        );
    }

    public static function afterStatus(Operation $operation, int $httpStatus): self
    {
        return new self(
            \sprintf('URSSAF operation %s answered HTTP %d: %s', $operation->value, $httpStatus, self::advice($operation)),
            $operation,
            $httpStatus,
            null,
        );
    }

    /**
     * The declared success status came with a body that does not match the contract.
     *
     * @param string $reason short technical reason (e.g. "missing idEnrolement"), never a payload value
     */
    public static function unreadableAnswer(Operation $operation, int $httpStatus, string $reason): self
    {
        return new self(
            \sprintf(
                'URSSAF operation %s answered HTTP %d with an unreadable response (%s): %s',
                $operation->value,
                $httpStatus,
                $reason,
                self::advice($operation),
            ),
            $operation,
            $httpStatus,
            null,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    /**
     * Null after a transport error (no HTTP response).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Class name of the transport exception, when there was one.
     */
    public function causeClass(): ?string
    {
        return $this->causeClass;
    }

    public function mayHaveReachedUrssaf(): bool
    {
        return true;
    }

    private static function advice(Operation $operation): string
    {
        return match ($operation) {
            Operation::SubmitPosting => 'the posting may have been recorded; do not resend it before checking with'
                . ' URSSAF outside the API (case manager, urssaf.fr).',
            Operation::RequestEnrolment => 'the enrolment may have been recorded; list the enrolments before resending'
                . ' it.',
            Operation::ListEnrolments,
            Operation::GetEnrolment,
            Operation::DeleteEnrolment,
            Operation::GetCertificate => 'the call may have been processed; check its effect before resending it.',
        };
    }
}

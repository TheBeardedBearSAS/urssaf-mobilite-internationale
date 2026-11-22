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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\RetryableException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;

/**
 * Transient failure: transport error, timeout, HTTP 429, 5xx on a read or on the enrolment deletion (ADR 0002,
 * ADR 0004). Retryable by the caller; the package never retries.
 *
 * {@see self::mayHaveReachedUrssaf()} tells whether URSSAF may have processed the call anyway: true for transport
 * errors and HTTP 5xx answers of the API call (a repeated DELETE is harmless; confirm with GetEnrolment); false for
 * 429 and for every failure of the token request (the API call itself was never sent). The two POST operations report
 * these failures as {@see OutcomeUnknown} instead. The transport cause is kept as a class name only, never its message.
 */
final class UrssafUnavailable extends RuntimeException implements RetryableException
{
    private const QUOTA_EXCEEDED_STATUS = 429;

    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ?int $retryAfterSeconds,
        private readonly bool $mayHaveReachedUrssaf,
        private readonly ?string $causeClass,
        private readonly ApiErrors $errors,
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }

    /**
     * The API call answered a transient HTTP status (5xx).
     *
     * @param int|null $retryAfterSeconds value of the Retry-After header (delta-seconds) when provided
     */
    public static function withStatus(
        Operation $operation,
        int $httpStatus,
        ?int $retryAfterSeconds,
        ApiErrors $errors,
    ): self {
        return new self(
            \sprintf('URSSAF operation %s is temporarily unavailable (HTTP %d).', $operation->value, $httpStatus),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            $httpStatus >= 500,
            null,
            $errors,
        );
    }

    /**
     * The API call answered HTTP 429 (quota exceeded): it was not processed.
     *
     * @param int|null $retryAfterSeconds value of the Retry-After header (delta-seconds) when provided
     */
    public static function quotaExceeded(Operation $operation, ?int $retryAfterSeconds, ApiErrors $errors): self
    {
        return new self(
            \sprintf('URSSAF quota exceeded for operation %s (HTTP 429).', $operation->value),
            $operation,
            self::QUOTA_EXCEEDED_STATUS,
            $retryAfterSeconds,
            false,
            null,
            $errors,
        );
    }

    /**
     * The API call failed without an HTTP answer (e.g. connection error, timeout).
     *
     * @param string $causeClass class name of the transport exception (its message is never kept)
     */
    public static function dueToTransportError(Operation $operation, string $causeClass): self
    {
        return new self(
            \sprintf('URSSAF operation %s could not be reached (%s).', $operation->value, $causeClass),
            $operation,
            null,
            null,
            true,
            $causeClass,
            ApiErrors::none(),
        );
    }

    /**
     * The access token needed by the call could not be obtained (HTTP 429 / 5xx of the token endpoint, or transport
     * error when $httpStatus is null): the API call itself was not sent.
     */
    public static function whileAuthenticating(
        Operation $operation,
        ?int $httpStatus,
        ?int $retryAfterSeconds,
        ?string $causeClass,
        ApiErrors $errors,
    ): self {
        return new self(
            \sprintf('URSSAF authentication for operation %s is temporarily unavailable.', $operation->value),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            false,
            $causeClass,
            $errors,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    /**
     * Null for transport errors (no HTTP response).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isQuotaExceeded(): bool
    {
        return $this->httpStatus === self::QUOTA_EXCEEDED_STATUS;
    }

    /**
     * True when URSSAF may have processed the call despite the failure (see the class documentation).
     */
    public function mayHaveReachedUrssaf(): bool
    {
        return $this->mayHaveReachedUrssaf;
    }

    /**
     * Class name of the transport exception, when there was one.
     */
    public function causeClass(): ?string
    {
        return $this->causeClass;
    }

    /**
     * The errors parsed from the answer body, when it had a readable one. Never logged.
     */
    public function errors(): ApiErrors
    {
        return $this->errors;
    }
}

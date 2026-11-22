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

use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\ErrorPayloadParser;

/**
 * Maps the HTTP answer of an API call to the declared success or to a gateway exception (ADR 0002 status table,
 * ADR 0004 for the two POST). Error bodies are parsed leniently by {@see ErrorPayloadParser} and kept on the
 * exception for the caller; they are never part of a message nor logged (ADR 0006).
 *
 * @internal
 */
final readonly class ResponseInterpreter
{
    public function __construct(private ErrorPayloadParser $errorParser = new ErrorPayloadParser()) {}

    /**
     * Returns the response when it is a success declared for the operation, throws otherwise.
     *
     * @throws UrssafRequestRejected      HTTP 400 on every operation, 403 on the posting
     * @throws UrssafAuthenticationFailed HTTP 401, 403 elsewhere than on the posting
     * @throws UrssafResourceNotFound     HTTP 404 on the enrolment operations and the certificate
     * @throws UrssafUnavailable          HTTP 429, and 5xx on a read or the deletion
     * @throws OutcomeUnknown             5xx or undeclared 2xx on a POST
     * @throws UnexpectedUrssafResponse   any other status
     */
    public function interpret(ApiRequest $request, ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();

        if ($request->declares($status)) {
            return $response;
        }

        $operation = $request->operation();

        throw match (intdiv($status, 100)) {
            2 => $this->undeclaredSuccess($operation, $status),
            4 => $this->clientFailure($operation, $status, $response),
            5 => $this->serverFailure($operation, $status, $response),
            default => UnexpectedUrssafResponse::unexpectedStatus($operation, $status),
        };
    }

    private function undeclaredSuccess(Operation $operation, int $status): OutcomeUnknown|UnexpectedUrssafResponse
    {
        return $operation->isUnsafeWrite()
            ? OutcomeUnknown::afterStatus($operation, $status)
            : UnexpectedUrssafResponse::unexpectedStatus($operation, $status);
    }

    private function clientFailure(
        Operation $operation,
        int $status,
        ResponseInterface $response,
    ): UrssafRequestRejected|UrssafAuthenticationFailed|UrssafResourceNotFound|UrssafUnavailable|UnexpectedUrssafResponse {
        return match (true) {
            $status === 400 => UrssafRequestRejected::withErrors($operation, $status, $this->errors($response)),
            $status === 401 => UrssafAuthenticationFailed::withStatus($status),
            $status === 403 => $operation === Operation::SubmitPosting
                ? UrssafRequestRejected::withErrors($operation, $status, $this->errors($response))
                : UrssafAuthenticationFailed::withStatus($status),
            $status === 404 && $operation !== Operation::SubmitPosting => UrssafResourceNotFound::withErrors(
                $operation,
                $this->errors($response),
            ),
            $status === 429 => UrssafUnavailable::quotaExceeded(
                $operation,
                RetryAfter::seconds($response),
                $this->errors($response),
            ),
            default => UnexpectedUrssafResponse::unexpectedStatus($operation, $status),
        };
    }

    private function serverFailure(
        Operation $operation,
        int $status,
        ResponseInterface $response,
    ): OutcomeUnknown|UrssafUnavailable {
        return $operation->isUnsafeWrite()
            ? OutcomeUnknown::afterStatus($operation, $status)
            : UrssafUnavailable::withStatus($operation, $status, RetryAfter::seconds($response), $this->errors($response));
    }

    private function errors(ResponseInterface $response): ApiErrors
    {
        return $this->errorParser->parse((string) $response->getBody());
    }
}

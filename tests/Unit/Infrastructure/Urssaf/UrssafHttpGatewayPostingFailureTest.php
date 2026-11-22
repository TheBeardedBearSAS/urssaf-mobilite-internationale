<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;
use Throwable;

final class UrssafHttpGatewayPostingFailureTest extends TestCase
{
    public function testRejectionsAreNothingRecorded(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '[{"code":"A","message":"mandatory data missing"},{"code":"B"}]');
        $stack->respond(403, '');
        $gateway = $stack->gateway();

        try {
            $gateway->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertCount(2, $exception->errors());
        }

        try {
            $gateway->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(403, $exception->httpStatus());
            static::assertCount(0, $exception->errors());
        }
    }

    public function testAnExpiredTokenIsRenewedAndThePostingReplayedOnce(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(200, '[{"id":"' . Calls::DOSSIER_ID . '"}]');

        $receipt = $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());

        static::assertSame(Calls::DOSSIER_ID, $receipt->first()->value());
        $api = $stack->apiRequests();
        static::assertCount(2, $api);
        static::assertSame((string) $api[0]->getBody(), (string) $api[1]->getBody());
    }

    public function testAQuotaFailureWasNotProcessedAndIsRetryable(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(429, '', ['Retry-After' => '7']);

        try {
            $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
            static::fail('A quota failure was expected.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->isQuotaExceeded());
            static::assertSame(7, $exception->retryAfterSeconds());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
    }

    public function testOtherStatusesAreUnexpectedAndNotProcessed(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(404, '{"code":"nf"}');
        $stack->respond(302, '');

        foreach ([404, 302] as $status) {
            try {
                $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
                static::fail('An unexpected response was expected.');
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertSame($status, $exception->getCode());
            }
        }
    }

    /**
     * @return iterable<string, array{int, string, array<string, string>}>
     */
    public static function failureAnswers(): iterable
    {
        yield 'rejected with the request echoed' => [400, '[{"code":"A","message":"LEAK-ECHO Usage Test 0000000000000"}]', []];
        yield 'forbidden' => [403, '{"message":"LEAK-ECHO"}', []];
        yield 'unauthorized' => [401, 'LEAK-ECHO', []];
        yield 'not found' => [404, 'LEAK-ECHO', []];
        yield 'quota' => [429, 'LEAK-ECHO', ['Retry-After' => '3']];
        yield 'server error' => [500, 'LEAK-ECHO', []];
        yield 'success' => [200, '[{"id":"' . Calls::DOSSIER_ID . '"}]', []];
        yield 'unreadable success' => [200, 'LEAK-ECHO', []];
    }

    /**
     * ADR 0006: no value of the synthetic request, no token, no secret and no URSSAF body in a log record or in an
     * exception message, whatever the answer.
     *
     * @param array<string, string> $headers
     */
    #[DataProvider('failureAnswers')]
    public function testNothingOfTheRequestTheTokenOrTheAnswerReachesTheLogsOrTheMessages(
        int $status,
        string $body,
        array $headers,
    ): void {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);
        $stack->respondToken();
        $stack->respond($status, $body, $headers);

        $messages = '';
        try {
            $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::full());
        } catch (Throwable $exception) {
            $messages = $exception->getMessage() . $exception::class;
        }

        $sent = (string) $stack->lastRequest()->getBody();
        static::assertStringContainsString('Usage Test', $sent, 'The request does carry the synthetic values.');
        $observed = $stack->logger->dump() . $messages;
        foreach (['Usage Test', 'Birth Test', 'First Test', '0000000000000', 'Free comment', 'LEAK-ECHO', Stack::ISSUED, 'fictitious-client-secret-value', 'Bearer'] as $forbidden) {
            static::assertStringNotContainsString($forbidden, $observed, $forbidden);
        }
    }

    public function testASecondAuthenticationFailureIsReported(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(401, '');

        $this->expectException(UrssafAuthenticationFailed::class);

        $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
    }
}

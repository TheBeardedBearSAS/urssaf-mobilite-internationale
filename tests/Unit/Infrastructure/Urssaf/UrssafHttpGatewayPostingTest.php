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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Exceptions;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;

final class UrssafHttpGatewayPostingTest extends TestCase
{
    private const SECOND_ID = '0b9f3f0e-0000-4000-8000-0000000000d2';

    public function testSubmitPostingPostsTheEncodedRequestAndReadsTheReceipt(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[{"id":"' . Calls::DOSSIER_ID . '"}]');
        $request = SyntheticPostings::full();

        $receipt = $stack->gateway()->submitPosting(Stacks::tenant(), $request);

        $sent = $stack->lastRequest();
        static::assertSame('POST', $sent->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/declaration/detachement/UE', $sent->getUri()->getPath());
        static::assertSame('application/json', $sent->getHeaderLine('Content-Type'));
        static::assertSame(JsonEncoder::encode((new PostingRequestEncoder())->encode($request)), (string) $sent->getBody());
        static::assertSame(Calls::DOSSIER_ID, $receipt->first()->value());
        static::assertCount(1, $receipt->dossierIds());
    }

    public function testAReceiptWithSeveralIdentifiersIsKeptWhole(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[{"id":"' . Calls::DOSSIER_ID . '"},{"id":"' . self::SECOND_ID . '"}]');

        $receipt = $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());

        static::assertCount(2, $receipt->dossierIds());
        static::assertSame(self::SECOND_ID, $receipt->dossierIds()[1]->value());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unknownOutcomes(): iterable
    {
        yield 'a 201 instead of the 200' => [201, '[{"id":"' . Calls::DOSSIER_ID . '"}]'];
        yield 'a 202' => [202, ''];
        yield 'a 204' => [204, ''];
        yield 'empty array' => [200, '[]'];
        yield 'empty object' => [200, '{}'];
        yield 'item without id' => [200, '[{"numero":"x"}]'];
        yield 'invalid uuid' => [200, '[{"id":"123"}]'];
        yield 'second item invalid' => [200, '[{"id":"' . Calls::DOSSIER_ID . '"},{"id":""}]'];
        yield 'a scalar item' => [200, '[3]'];
        yield 'an object instead of the array' => [200, '{"id":"' . Calls::DOSSIER_ID . '"}'];
        yield 'invalid json' => [200, '<html>'];
        yield 'empty body' => [200, ''];
        yield 'internal error without body' => [500, ''];
        yield 'internal error with a body' => [500, '{"code":"boom"}'];
        yield 'bad gateway' => [502, '<html>'];
        yield 'unavailable' => [503, ''];
    }

    #[DataProvider('unknownOutcomes')]
    public function testSubmitPostingAnswersWithAnUnknownOutcomeThatNeverContainsTheRequest(int $status, string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body);

        try {
            $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::full());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertFalse(Exceptions::isRetryable($exception));
            static::assertSame(Operation::SubmitPosting, $exception->operation());
            static::assertSame($status, $exception->httpStatus());
            static::assertStringContainsString('do not resend', $exception->getMessage());
            static::assertStringNotContainsString('Usage Test', $exception->getMessage());
        }
        static::assertCount(1, $stack->apiRequests(), 'The posting is never resent.');
    }

    public function testSubmitPostingAnswersWithAnUnknownOutcomeAfterATransportError(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('cURL error 28: timeout at https://api-edi.urssaf.fr'));

        try {
            $stack->gateway()->submitPosting(Stacks::tenant(), SyntheticPostings::minimal());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $exception) {
            static::assertNull($exception->httpStatus());
            static::assertSame(TransportFailure::class, $exception->causeClass());
            static::assertStringNotContainsString('cURL', $exception->getMessage());
            static::assertNull($exception->getPrevious());
        }
        static::assertCount(1, $stack->apiRequests());
    }
}

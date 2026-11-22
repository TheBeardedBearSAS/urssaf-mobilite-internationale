<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class ApiResponseTest extends TestCase
{
    public function testItExposesTheStatusAndThePayload(): void
    {
        $payload = JsonDecoder::decode('{"a":"b"}');

        $response = new ApiResponse(201, $payload);

        static::assertSame(201, $response->status());
        static::assertSame($payload, $response->payload());
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function unsafeWrites(): iterable
    {
        yield 'enrolment creation' => [Operation::RequestEnrolment];
        yield 'posting' => [Operation::SubmitPosting];
    }

    #[DataProvider('unsafeWrites')]
    public function testAnUnreadableAnswerOfAPostIsAnUnknownOutcomeWithoutPayloadValue(Operation $operation): void
    {
        $failure = ApiResponse::unreadable($operation, 201, UnreadablePayload::because('field "id" is missing or empty'));

        static::assertInstanceOf(OutcomeUnknown::class, $failure);
        static::assertSame($operation, $failure->operation());
        static::assertSame(201, $failure->httpStatus());
        static::assertStringContainsString('field "id" is missing or empty', $failure->getMessage());
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function reads(): iterable
    {
        yield 'enrolment list' => [Operation::ListEnrolments];
        yield 'enrolment read' => [Operation::GetEnrolment];
        yield 'enrolment deletion' => [Operation::DeleteEnrolment];
        yield 'certificate' => [Operation::GetCertificate];
    }

    #[DataProvider('reads')]
    public function testAnUnreadableAnswerOfAReadIsAnUnexpectedResponse(Operation $operation): void
    {
        $previous = UnreadablePayload::because('expected a JSON array');

        $failure = ApiResponse::unreadable($operation, 200, $previous);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $failure);
        static::assertSame('Unexpected URSSAF response: expected a JSON array.', $failure->getMessage());
        static::assertSame($previous, $failure->getPrevious());
    }
}

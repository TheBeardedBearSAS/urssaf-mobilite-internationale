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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayEnrolmentReadTest extends TestCase
{
    private const ID = Calls::ENROLMENT_ID;

    public function testListEnrolmentsSendsFlatQueryParametersAndBuildsThePage(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[' . Calls::ENROLMENT . ',{"idEnrolement":"0b9f3f0e-0000-4000-8000-000000000002"}]');

        $page = $stack->gateway()->listEnrolments(Stacks::tenant(), 20, 5, 'dateDemande,DESC');

        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/enrolements', $request->getUri()->getPath());
        static::assertSame('offset=20&limit=5&sort=dateDemande%2CDESC', $request->getUri()->getQuery());
        static::assertCount(2, $page->enrolments());
        static::assertSame(20, $page->offset());
        static::assertSame(5, $page->limit());
        static::assertTrue($page->isLastPage());
        static::assertNull($page->nextOffset());
    }

    public function testListEnrolmentsOmitsAnAbsentSortAndReportsAFullPage(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[' . Calls::ENROLMENT . ']');

        $page = $stack->gateway()->listEnrolments(Stacks::tenant(), 0, 1, null);

        static::assertSame('offset=0&limit=1', $stack->lastRequest()->getUri()->getQuery());
        static::assertFalse($page->isLastPage());
        static::assertSame(1, $page->nextOffset());
    }

    public function testListEnrolmentsAcceptsAnEmptyList(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '[]');

        static::assertSame([], $stack->gateway()->listEnrolments(Stacks::tenant(), 0, 20, null)->enrolments());
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'negative offset' => [-1, 20, 'offset.negative'];
        yield 'zero limit' => [0, 0, 'limit.not_positive'];
        yield 'negative limit' => [0, -5, 'limit.not_positive'];
    }

    #[DataProvider('invalidPages')]
    public function testListEnrolmentsRefusesAnInvalidPageBeforeAnyCall(int $offset, int $limit, string $code): void
    {
        $stack = Stacks::sandbox();

        try {
            $stack->gateway()->listEnrolments(Stacks::tenant(), $offset, $limit, null);
            static::fail('The page must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
        static::assertSame([], $stack->requests(), 'Not even a token request.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableLists(): iterable
    {
        yield 'an object' => ['{"idEnrolement":"' . self::ID . '"}'];
        yield 'invalid json' => ['<html>'];
        yield 'empty body' => [''];
        yield 'a list of scalars' => ['[1,2]'];
        yield 'an unreadable item' => ['[{"siren":"000000000"}]'];
    }

    #[DataProvider('unreadableLists')]
    public function testAnUnreadableListIsAnUnexpectedResponseNotAnUnknownOutcome(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, $body);

        $this->expectException(UnexpectedUrssafResponse::class);

        $stack->gateway()->listEnrolments(Stacks::tenant(), 0, 20, null);
    }

    public function testGetEnrolmentReadsTheEnrolment(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, Calls::ENROLMENT);

        $enrolment = $stack->gateway()->getEnrolment(Stacks::tenant(), EnrolmentId::fromString(self::ID));

        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/enrolements/' . self::ID, $request->getUri()->getPath());
        static::assertSame('', $request->getUri()->getQuery());
        static::assertSame('000000000', $enrolment->siren()?->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableEnrolments(): iterable
    {
        yield 'invalid json' => ['<html>'];
        yield 'empty body' => [''];
        yield 'a list' => ['[{"idEnrolement":"' . self::ID . '"}]'];
        yield 'missing identifier' => ['{"siren":"000000000"}'];
    }

    #[DataProvider('unreadableEnrolments')]
    public function testAnUnreadableEnrolmentIsAnUnexpectedResponse(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, $body);

        $this->expectException(UnexpectedUrssafResponse::class);

        $stack->gateway()->getEnrolment(Stacks::tenant(), EnrolmentId::fromString(self::ID));
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiRequest;

final class ApiRequestTest extends TestCase
{
    public function testGetCarriesAFlatQueryAndNoBody(): void
    {
        $request = ApiRequest::get(Operation::ListEnrolments, '/enrolements', ['offset' => '0', 'limit' => '20']);

        static::assertSame(Operation::ListEnrolments, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/enrolements', $request->path());
        static::assertSame(['offset' => '0', 'limit' => '20'], $request->query());
        static::assertNull($request->jsonBody());
        static::assertFalse($request->isDownload());
    }

    public function testGetHasNoQueryByDefault(): void
    {
        static::assertSame([], ApiRequest::get(Operation::GetEnrolment, '/enrolements/x')->query());
    }

    public function testPostCarriesAJsonBodyAndNoQuery(): void
    {
        $request = ApiRequest::post(Operation::SubmitPosting, '/declaration/detachement/UE', '{"a":1}');

        static::assertSame(Operation::SubmitPosting, $request->operation());
        static::assertSame('POST', $request->method());
        static::assertSame('/declaration/detachement/UE', $request->path());
        static::assertSame([], $request->query());
        static::assertSame('{"a":1}', $request->jsonBody());
        static::assertFalse($request->isDownload());
    }

    public function testTheBodyIsMaskedInDebugOutput(): void
    {
        $request = ApiRequest::post(Operation::SubmitPosting, '/declaration/detachement/UE', '{"nir":"secret-body"}');

        static::assertSame(
            [
                'operation' => Operation::SubmitPosting,
                'method' => 'POST',
                'path' => '/declaration/detachement/UE',
                'query' => [],
                'jsonBody' => '***',
                'download' => false,
            ],
            $request->__debugInfo(),
        );
        static::assertStringNotContainsString('secret-body', print_r($request, true));
        static::assertNull(ApiRequest::get(Operation::GetEnrolment, '/enrolements/x', ['limit' => 1])->__debugInfo()['jsonBody']);
    }

    public function testItRefusesToBeSerialized(): void
    {
        $request = ApiRequest::post(Operation::SubmitPosting, '/declaration/detachement/UE', '{"nir":"secret-body"}');

        $this->expectException(PersonalDataNotSerializable::class);

        serialize($request);
    }

    public function testItRefusesToBeUnserialized(): void
    {
        $this->expectException(PersonalDataNotSerializable::class);

        unserialize('O:' . \strlen(ApiRequest::class) . ':"' . ApiRequest::class . '":0:{}');
    }

    public function testDeleteHasNeitherQueryNorBody(): void
    {
        $request = ApiRequest::delete(Operation::DeleteEnrolment, '/enrolements/x');

        static::assertSame(Operation::DeleteEnrolment, $request->operation());
        static::assertSame('DELETE', $request->method());
        static::assertSame('/enrolements/x', $request->path());
        static::assertSame([], $request->query());
        static::assertNull($request->jsonBody());
        static::assertFalse($request->isDownload());
    }

    public function testDownloadIsAGetOfABinaryFile(): void
    {
        $request = ApiRequest::download(Operation::GetCertificate, '/certificat/x');

        static::assertSame(Operation::GetCertificate, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/certificat/x', $request->path());
        static::assertSame([], $request->query());
        static::assertNull($request->jsonBody());
        static::assertTrue($request->isDownload());
    }

    /**
     * @return iterable<string, array{Operation, list<int>}>
     */
    public static function declaredStatuses(): iterable
    {
        yield 'enrolment creation' => [Operation::RequestEnrolment, [201]];
        yield 'enrolment list' => [Operation::ListEnrolments, [200]];
        yield 'enrolment read' => [Operation::GetEnrolment, [200]];
        yield 'enrolment deletion' => [Operation::DeleteEnrolment, [200]];
        yield 'posting' => [Operation::SubmitPosting, [200]];
        yield 'certificate' => [Operation::GetCertificate, [200, 204]];
    }

    /**
     * @param list<int> $declared
     */
    #[DataProvider('declaredStatuses')]
    public function testItDeclaresExactlyTheSuccessStatusesOfTheContract(Operation $operation, array $declared): void
    {
        $request = ApiRequest::get($operation, '/x');

        for ($status = 100; $status < 600; ++$status) {
            static::assertSame(\in_array($status, $declared, true), $request->declares($status), 'HTTP ' . $status);
        }
    }
}

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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRequestTest extends TestCase
{
    public function testAGetSendsABearerTokenAndNoBodyHeader(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]');

        Calls::run($stack, Operation::ListEnrolments);

        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/enrolements?offset=0&limit=20',
            (string) $request->getUri(),
        );
        static::assertSame('Bearer ' . Stack::ISSUED, $request->getHeaderLine('Authorization'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
        static::assertSame(
            ['Host', 'Authorization', 'Accept', 'User-Agent'],
            array_keys($request->getHeaders()),
            'No request id, no application id and no other header by default.',
        );
    }

    public function testAPostSendsTheJsonBodyVerbatimWithItsContentType(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[{"id":"' . Calls::DOSSIER_ID . '"}]');

        Calls::run($stack, Operation::SubmitPosting);

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/declaration/detachement/UE',
            (string) $request->getUri(),
        );
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame('{}', (string) $request->getBody());
    }

    public function testADeleteHasNoBodyNorContentType(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '');

        Calls::run($stack, Operation::DeleteEnrolment);

        $request = $stack->lastRequest();
        static::assertSame('DELETE', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/enrolements/' . Calls::ENROLMENT_ID,
            (string) $request->getUri(),
        );
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testADownloadAsksForAPdfFirst(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        Calls::succeed($stack, Operation::GetCertificate);

        Calls::run($stack, Operation::GetCertificate);

        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame('application/pdf, application/json', $request->getHeaderLine('Accept'));
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/certificat/' . Calls::DOSSIER_ID,
            (string) $request->getUri(),
        );
        static::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testTheQueryIsFlatAndRfc3986Encoded(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::ListEnrolments, '/enrolements', [
                'offset' => '10',
                'limit' => '5',
                'sort' => 'dateDemande DESC&x=1',
            ]),
        );

        static::assertSame(
            'offset=10&limit=5&sort=dateDemande%20DESC%26x%3D1',
            $stack->lastRequest()->getUri()->getQuery(),
        );
    }

    public function testTheOverriddenUrisAndUserAgentAreUsed(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(
            Environment::Production,
            'https://mock.example.test/base',
            'https://token.example.test/token',
            null,
            60,
            'custom-agent/2',
        ));
        $stack->respondToken();
        $stack->respond(200, '{"idEnrolement":"' . Calls::ENROLMENT_ID . '"}');

        Calls::run($stack, Operation::GetEnrolment);

        $requests = $stack->requests();
        static::assertSame('https://token.example.test/token', (string) $requests[0]->getUri());
        static::assertSame('custom-agent/2', $requests[0]->getHeaderLine('User-Agent'));
        static::assertSame('https://mock.example.test/base/enrolements/' . Calls::ENROLMENT_ID, (string) $requests[1]->getUri());
        static::assertSame('custom-agent/2', $requests[1]->getHeaderLine('User-Agent'));
    }
}

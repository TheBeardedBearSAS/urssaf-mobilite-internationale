<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPosting;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPostingHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolments;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;

/**
 * The options of the bundle reach the HTTP calls of the handlers (mocked PSR-18 client, synthetic data only).
 *
 * @mago-expect lint:too-many-methods One test per option that changes the calls.
 */
final class RequestWiringTest extends TestCase
{
    public function testTheFirstPageIsRequestedWithTheBearerToken(): void
    {
        $harness = Harness::boot();

        $page = self::list($harness);

        static::assertCount(1, $page->enrolments());
        $request = self::lastRequest($harness);
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/mobilite/enrolements', explode('?', (string) $request->getUri())[0]);
        static::assertSame('offset=0&limit=20', $request->getUri()->getQuery());
        static::assertSame('Bearer fictitious-access-token', $request->getHeaderLine('Authorization'));
    }

    public function testTheBaseUriAndTheTokenUriOverridesAreUsed(): void
    {
        $harness = Harness::boot(['base_uri' => 'https://mock.example.test/mi', 'token_uri' => 'https://auth.example.test/oauth/token']);

        self::list($harness);

        $requests = $harness->client->getRequests();
        static::assertSame('https://auth.example.test/oauth/token', (string) $requests[0]->getUri());
        static::assertSame('mock.example.test', $requests[1]->getUri()->getHost());
        static::assertSame('/mi/enrolements', $requests[1]->getUri()->getPath());
    }

    public function testTheUserAgentAndTheRequestIdHeaderAreSent(): void
    {
        $harness = Harness::boot(['user_agent' => 'host-app/1.0', 'headers' => ['request_id' => 'x-request-id']]);

        self::list($harness);

        $request = self::lastRequest($harness);
        static::assertSame('host-app/1.0', $request->getHeaderLine('User-Agent'));
        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $request->getHeaderLine('x-request-id'));
    }

    public function testNoRequestIdHeaderIsSentByDefault(): void
    {
        $harness = Harness::boot();

        self::list($harness);

        static::assertFalse(self::lastRequest($harness)->hasHeader('x-request-id'));
    }

    public function testTheAccessTokenIsRequestedOnceForSeveralCalls(): void
    {
        $harness = Harness::boot();

        self::list($harness);
        self::list($harness);

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_LIST));
    }

    public function testTheSecretOfTheConfiguredCredentialsIsSentToTheTokenEndpoint(): void
    {
        $harness = Harness::boot();

        self::list($harness);

        $body = (string) $harness->client->getRequests()[0]->getBody();
        static::assertStringContainsString('client_id=client-1', $body);
        static::assertStringContainsString('client_secret=' . TestKernel::SECRET, $body);
    }

    public function testTheGatewayLogsOnTheApplicationLogger(): void
    {
        $harness = Harness::boot();

        self::list($harness);

        $logger = $harness->privateService('logger', CapturingLogger::class);
        static::assertSame(['URSSAF token request.', 'URSSAF call.'], array_map(static fn(array $record): string => $record['message'], $logger->records()));
        static::assertStringNotContainsString(TestKernel::SECRET, $logger->dump());
    }

    public function testTheRateLimiterIsOffByDefault(): void
    {
        $harness = Harness::boot();

        for ($call = 0; $call < 201; ++$call) {
            self::list($harness);
        }

        static::assertSame(201, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_LIST));
    }

    public function testTheRateLimiterEnforcesThePublishedQuotaWhenEnabled(): void
    {
        $harness = Harness::boot(['rate_limiter' => ['enabled' => true]]);
        for ($call = 0; $call < 200; ++$call) {
            self::list($harness);
        }

        try {
            self::list($harness);
            static::fail('The 201st call of the minute must be refused.');
        } catch (RateLimitExceeded) {
            static::assertSame(200, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_LIST));
        }
    }

    public function testACertificateBadRequestMeansNotAvailableYetByDefault(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 400, '');

        $result = $harness->get(GetCertificateHandler::class)(new GetCertificate(EnrolmentSamples::tenant(), EnrolmentSamples::dossierId()));

        static::assertInstanceOf(CertificateInProgress::class, $result);
        static::assertSame(400, $result->httpStatus());
    }

    public function testACertificateBadRequestIsARejectedRequestWhenConfigured(): void
    {
        $harness = Harness::boot(['certificate' => ['bad_request_as_in_progress' => false]]);
        $harness->urssaf->respond(HttpScript::ROUTE_CERTIFICATE, 400, '');

        $this->expectException(UrssafRequestRejected::class);

        $harness->get(GetCertificateHandler::class)(new GetCertificate(EnrolmentSamples::tenant(), EnrolmentSamples::dossierId()));
    }

    public function testThePostingIsSubmittedWithTheDefaultPolicy(): void
    {
        $harness = Harness::boot();

        $receipt = $harness->get(SubmitPostingHandler::class)(SubmitPosting::of(EnrolmentSamples::tenant(), self::teleworkWithoutCountry()));

        static::assertSame(Calls::DOSSIER_ID, $receipt->first()->value());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_POSTING));
    }

    public function testThePdfTeleworkRulesRefuseThePostingBeforeAnyCallWhenEnabled(): void
    {
        $harness = Harness::boot(['validation' => ['pdf_telework_rules' => true]]);

        try {
            $harness->get(SubmitPostingHandler::class)(SubmitPosting::of(EnrolmentSamples::tenant(), self::teleworkWithoutCountry()));
            static::fail('The telework rules must refuse the posting.');
        } catch (ValidationFailed) {
            static::assertSame([], $harness->client->getRequests());
        }
    }

    private static function teleworkWithoutCountry(): PostingRequest
    {
        return SyntheticPostings::withDetails(
            PostingDetails::of(null, null, null, Telework::withTelework(null, null, 2), null, null, null, null, null, [], null),
        );
    }

    private static function list(Harness $harness): EnrolmentPage
    {
        return $harness->get(ListEnrolmentsHandler::class)(ListEnrolments::firstPage(EnrolmentSamples::tenant()));
    }

    private static function lastRequest(Harness $harness): RequestInterface
    {
        $requests = $harness->client->getRequests();
        $last = end($requests);
        static::assertInstanceOf(RequestInterface::class, $last);

        return $last;
    }
}

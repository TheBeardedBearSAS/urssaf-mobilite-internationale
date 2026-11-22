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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

final class UrssafHttpGatewayCertificateTest extends TestCase
{
    private const PDF = "%PDF-1.7 synthetic\x00\xFF binary";

    public function testA200IsTheStreamedCertificate(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, self::PDF, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="x.pdf"']);

        $result = $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateAvailable::class, $result);
        static::assertSame(200, $result->httpStatus());
        static::assertSame('application/pdf', $result->file()->contentType());
        static::assertSame('attachment; filename="x.pdf"', $result->file()->contentDisposition());
        static::assertSame(self::PDF, $result->file()->stream()->read(1000));
        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame('/tiersdecl/v1/mobilite/certificat/' . Calls::DOSSIER_ID, $request->getUri()->getPath());
        static::assertSame('application/pdf, application/json', $request->getHeaderLine('Accept'));
    }

    public function testA204IsInProgressWithoutErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(204, '');

        $result = $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateInProgress::class, $result);
        static::assertSame(204, $result->httpStatus());
        static::assertTrue($result->errors()->isEmpty());
    }

    public function testA400IsInProgressByDefaultAndKeepsItsErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '{"code":"pending","message":"not available yet"}');

        $result = $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateInProgress::class, $result);
        static::assertSame(400, $result->httpStatus());
        static::assertTrue($result->errors()->hasCode('pending'));
    }

    public function testA400IsARejectionWhenTheOptionIsOff(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '{"code":"bad"}');

        try {
            $stack->gatewayWith(GatewayOptions::defaults()->withCertificateBadRequestAsInProgress(false))
                ->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertTrue($exception->hasErrorCode('bad'));
        }
    }

    public function testTheOptionOnKeepsA400InProgress(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(400, '');

        $result = $stack->gatewayWith(GatewayOptions::defaults()->withCertificateBadRequestAsInProgress(true))
            ->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateInProgress::class, $result);
    }

    public function testA404IsRefusedOrUnknownAndKeepsItsErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(404, '{"code":"refused","message":"file refused"}');

        $result = $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateRefusedOrUnknown::class, $result);
        static::assertSame(404, $result->httpStatus());
        static::assertTrue($result->errors()->hasCode('refused'));
    }

    public function testA404WithoutBodyIsRefusedOrUnknownWithoutErrors(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(404, '');

        $result = $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));

        static::assertInstanceOf(CertificateRefusedOrUnknown::class, $result);
        static::assertTrue($result->errors()->isEmpty());
    }

    /**
     * @return iterable<string, array{int, class-string<Throwable>}>
     */
    public static function failures(): iterable
    {
        yield '401 after the replay' => [401, UrssafAuthenticationFailed::class];
        yield '403' => [403, UrssafAuthenticationFailed::class];
        yield '429' => [429, UrssafUnavailable::class];
        yield '500' => [500, UrssafUnavailable::class];
        yield '503' => [503, UrssafUnavailable::class];
        yield '302' => [302, UnexpectedUrssafResponse::class];
        yield '418' => [418, UnexpectedUrssafResponse::class];
        yield '201' => [201, UnexpectedUrssafResponse::class];
        yield '202' => [202, UnexpectedUrssafResponse::class];
    }

    /**
     * @param class-string<Throwable> $class
     */
    #[DataProvider('failures')]
    public function testEveryOtherStatusIsAFailureNotAResult(int $status, string $class): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '');
        $stack->respondToken();
        $stack->respond($status, '');

        try {
            $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));
            static::fail('A failure was expected.');
        } catch (Throwable $exception) {
            static::assertInstanceOf($class, $exception);
            static::assertNotInstanceOf(OutcomeUnknown::class, $exception, 'The certificate is a read.');
        }
    }

    public function testATransportErrorOnTheCertificateIsUnavailableNotAnUnknownOutcome(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('reset'));

        try {
            $stack->gateway()->getCertificate(Stacks::tenant(), DossierId::fromString(Calls::DOSSIER_ID));
            static::fail('A failure was expected.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->mayHaveReachedUrssaf());
            static::assertSame(TransportFailure::class, $exception->causeClass());
        }
    }
}

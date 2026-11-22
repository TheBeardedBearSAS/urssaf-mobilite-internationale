<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPosting;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPostingHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Exception\EnrolmentNotAccepted;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\FakeMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire\SyntheticPostings;

#[CoversClass(SubmitPosting::class)]
#[CoversClass(SubmitPostingHandler::class)]
#[CoversClass(EnrolmentNotAccepted::class)]
final class SubmitPostingHandlerTest extends TestCase
{
    public function testTheDefaultCommandDoesNotCheckTheEnrolment(): void
    {
        $receipt = PostingReceipt::of(EnrolmentSamples::dossierId());
        $gateway = new FakeMobilityGateway();
        $gateway->willSubmitPosting($receipt);
        $request = SyntheticPostings::minimal();
        $command = SubmitPosting::of(EnrolmentSamples::tenant(), $request);

        $result = (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))($command);

        static::assertSame($receipt, $result);
        static::assertSame([Operation::SubmitPosting], $gateway->operations());
        static::assertSame([$request], $gateway->calls()[0]['arguments']);
        static::assertSame(EnrolmentSamples::tenant()->value(), $gateway->calls()[0]['tenantId']->value());
        static::assertNull($command->enrolmentToCheck);
        static::assertSame($request, $command->request);
        static::assertSame(EnrolmentSamples::tenant()->value(), $command->tenantId->value());
    }

    public function testTheGuardReadsTheEnrolmentThenSubmitsWhenItIsAcceptedForTheSameCompany(): void
    {
        $receipt = PostingReceipt::of(EnrolmentSamples::dossierId());
        $gateway = new FakeMobilityGateway();
        $gateway->willGetEnrolment(EnrolmentSamples::enrolment('ACCEPTE'));
        $gateway->willSubmitPosting($receipt);
        $command = SubmitPosting::afterCheckingEnrolment(
            EnrolmentSamples::tenant(),
            SyntheticPostings::minimal(),
            EnrolmentSamples::enrolmentId(),
        );

        $result = (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))($command);

        static::assertSame($receipt, $result);
        static::assertSame([Operation::GetEnrolment, Operation::SubmitPosting], $gateway->operations());
        static::assertEquals([EnrolmentSamples::enrolmentId()], $gateway->calls()[0]['arguments']);
        static::assertSame(EnrolmentSamples::tenant()->value(), $gateway->calls()[0]['tenantId']->value());
        static::assertSame(EnrolmentSamples::tenant()->value(), $gateway->calls()[1]['tenantId']->value());
        static::assertEquals(EnrolmentSamples::enrolmentId(), $command->enrolmentToCheck);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function refusedEnrolments(): iterable
    {
        yield 'pending' => ['ENCOURS', EnrolmentSamples::SIREN, 'status'];
        yield 'refused' => ['REFUSE', EnrolmentSamples::SIREN, 'status'];
        yield 'deleted' => ['SUPPRIME', EnrolmentSamples::SIREN, 'status'];
        yield 'unknown status' => ['NOUVEAU', EnrolmentSamples::SIREN, 'status'];
        yield 'absent status' => [null, EnrolmentSamples::SIREN, 'status'];
        yield 'other company' => ['ACCEPTE', '111111111', 'siren'];
        yield 'absent siren' => ['ACCEPTE', null, 'siren'];
    }

    #[DataProvider('refusedEnrolments')]
    public function testTheGuardRefusesAndNothingIsSubmitted(?string $status, ?string $siren, string $reason): void
    {
        $gateway = new FakeMobilityGateway();
        $gateway->willGetEnrolment(EnrolmentSamples::enrolment($status, $siren));
        $command = SubmitPosting::afterCheckingEnrolment(
            EnrolmentSamples::tenant(),
            SyntheticPostings::minimal(),
            EnrolmentSamples::enrolmentId(),
        );

        try {
            (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))($command);
            static::fail('The guard must refuse the enrolment.');
        } catch (EnrolmentNotAccepted $exception) {
            static::assertSame('enrolment.not_accepted', $exception->violationCode());
            static::assertSame(['reason' => $reason], $exception->params());
            static::assertStringContainsString(EnrolmentSamples::ENROLMENT_ID, $exception->getMessage());
        }
        static::assertSame([Operation::GetEnrolment], $gateway->operations());
    }

    public function testTheGuardMessagesAreDistinct(): void
    {
        $id = EnrolmentSamples::enrolmentId();

        static::assertSame('Enrolment "' . EnrolmentSamples::ENROLMENT_ID . '" is not accepted: its status is not ACCEPTE.', EnrolmentNotAccepted::notAccepted($id)->getMessage());
        static::assertSame(
            'Enrolment "' . EnrolmentSamples::ENROLMENT_ID . '" is not the enrolment of the referenced company: the SIREN differs.',
            EnrolmentNotAccepted::otherCompany($id)->getMessage(),
        );
    }

    public function testThePolicyIsAppliedBeforeAnyCall(): void
    {
        $gateway = new FakeMobilityGateway();
        $handler = new SubmitPostingHandler($gateway, PostingValidationPolicy::default()->withPdfTeleworkRules());
        $request = self::teleworkWithoutCountry();

        foreach ([
            SubmitPosting::of(EnrolmentSamples::tenant(), $request),
            SubmitPosting::afterCheckingEnrolment(EnrolmentSamples::tenant(), $request, EnrolmentSamples::enrolmentId()),
        ] as $command) {
            try {
                $handler($command);
                static::fail('The policy must refuse the request.');
            } catch (ValidationFailed $exception) {
                static::assertCount(2, $exception->result()->all());
            }
        }
        static::assertSame([], $gateway->calls());
    }

    public function testTheDefaultPolicyAcceptsTheSameRequest(): void
    {
        $gateway = new FakeMobilityGateway();
        $gateway->willSubmitPosting(PostingReceipt::of(EnrolmentSamples::dossierId()));

        (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))(SubmitPosting::of(EnrolmentSamples::tenant(), self::teleworkWithoutCountry()));

        static::assertSame([Operation::SubmitPosting], $gateway->operations());
    }

    public function testAGatewayFailureIsNeitherRetriedNorResent(): void
    {
        $failure = new RuntimeException('boom');
        $gateway = new FakeMobilityGateway();
        $gateway->willSubmitPosting($failure);

        try {
            (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))(SubmitPosting::of(EnrolmentSamples::tenant(), SyntheticPostings::minimal()));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertSame([Operation::SubmitPosting], $gateway->operations());
    }

    public function testAFailureOfTheGuardReadPropagatesWithoutSubmission(): void
    {
        $failure = new RuntimeException('boom');
        $gateway = new FakeMobilityGateway();
        $gateway->willGetEnrolment($failure);

        try {
            (new SubmitPostingHandler($gateway, PostingValidationPolicy::default()))(SubmitPosting::afterCheckingEnrolment(
                EnrolmentSamples::tenant(),
                SyntheticPostings::minimal(),
                EnrolmentSamples::enrolmentId(),
            ));
            static::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertSame([Operation::GetEnrolment], $gateway->operations());
    }

    private static function teleworkWithoutCountry(): PostingRequest
    {
        return SyntheticPostings::withDetails(
            PostingDetails::of(null, null, null, Telework::withTelework(null, null, 2), null, null, null, null, null, [], null),
        );
    }
}

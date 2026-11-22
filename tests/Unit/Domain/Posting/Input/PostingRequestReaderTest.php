<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompletePostingDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarantDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\IncompletePostingDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingDetailsDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\TeleworkDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;

final class PostingRequestReaderTest extends TestCase
{
    public function testCompleteDraft(): void
    {
        $reading = PostingRequestReader::read(PostingDrafts::request(PostingDrafts::details()), PostingValidationPolicy::default()->withPdfTeleworkRules());

        static::assertInstanceOf(CompletePostingDraft::class, $reading);
        static::assertTrue($reading->result()->isValid());
        $request = $reading->toPostingRequest();
        static::assertSame('Nom exemple', $request->declarant()->name()->value());
        static::assertSame('test@example.invalid', $request->declarant()->email()->value());
        static::assertSame('0000000003', $request->declarant()->phone1()->value());
        static::assertNull($request->declarant()->phone2());
        static::assertSame('404833048', $request->declaration()->companyReference()->siren()->value());
        static::assertSame('2690549588157', $request->declaration()->posting()->worker()?->nir()->value());
        static::assertSame(['result' => '***', 'request' => '***'], $reading->__debugInfo());
    }

    public function testFromDraft(): void
    {
        $request = PostingRequest::fromDraft(PostingDrafts::request(PostingDrafts::emptyDetails()), PostingValidationPolicy::default());

        static::assertSame('0000000003', $request->declarant()->phone1()->value());
        static::assertNull($request->declaration()->posting()->telework());
    }

    public function testEmptyDraft(): void
    {
        $reading = PostingRequestReader::read(new PostingRequestDraft(null, null), PostingValidationPolicy::default());

        static::assertInstanceOf(IncompletePostingDraft::class, $reading);
        static::assertSame(['declarant field.required', 'declaration field.required'], ViolationSummary::of($reading->result()));
        try {
            $reading->toPostingRequest();
            static::fail('ValidationFailed expected.');
        } catch (ValidationFailed $failed) {
            static::assertSame($reading->result(), $failed->result());
        }
    }

    /**
     * @return iterable<string, array{PostingRequestDraft, list<string>}>
     */
    public static function provideIncompleteDrafts(): iterable
    {
        $declaration = PostingDrafts::declaration(PostingDrafts::emptyDetails());
        $declarant = PostingDrafts::declarant();
        yield 'declarant' => [new PostingRequestDraft(null, $declaration), ['declarant field.required']];
        yield 'declaration' => [new PostingRequestDraft($declarant, null), ['declaration field.required']];
        yield 'name' => [new PostingRequestDraft(new DeclarantDraft(null, 'test@example.invalid', '0000000003', null), $declaration), ['declarant.name field.required']];
        yield 'email' => [new PostingRequestDraft(new DeclarantDraft('', null, '0000000003', null), $declaration), ['declarant.email field.required']];
        yield 'phone1' => [new PostingRequestDraft(new DeclarantDraft('', 'test@example.invalid', null, '0000000004'), $declaration), ['declarant.phone1 field.required']];
        yield 'invalid declarant' => [
            new PostingRequestDraft(new DeclarantDraft(str_repeat('x', 256), 'test', '123456', '123456'), $declaration),
            [
                'declarant.name label.too_long',
                'declarant.email email_address.invalid_format',
                'declarant.phone1 phone_number.invalid_length',
                'declarant.phone2 phone_number.invalid_length',
            ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideIncompleteDrafts')]
    public function testIncompleteDraft(PostingRequestDraft $draft, array $expected): void
    {
        $reading = PostingRequestReader::read($draft, PostingValidationPolicy::default());

        static::assertInstanceOf(IncompletePostingDraft::class, $reading);
        static::assertSame($expected, ViolationSummary::of($reading->result()));
    }

    public function testInvalidOptionalPartKeepsTheDraftCompleteButInvalid(): void
    {
        $details = new PostingDetailsDraft(null, '2026-02-30', null, null, null, null, null, null, null, [], null);

        $reading = PostingRequestReader::read(PostingDrafts::request($details), PostingValidationPolicy::default());

        static::assertInstanceOf(CompletePostingDraft::class, $reading);
        static::assertSame(['declaration.posting.startDate calendar_date.invalid_date'], ViolationSummary::of($reading->result()));
        $this->expectException(ValidationFailed::class);
        PostingRequest::fromDraft(PostingDrafts::request($details), PostingValidationPolicy::default());
    }

    public function testPolicyIsApplied(): void
    {
        $draft = PostingDrafts::request(self::teleworking(new TeleworkDraft(true, null, null, 2)));

        static::assertTrue(PostingRequestReader::read($draft, PostingValidationPolicy::default())->result()->isValid());
        static::assertSame(
            ['declaration.posting.telework.country field.required', 'declaration.posting.telework.workerAgreement field.required'],
            ViolationSummary::of(PostingRequestReader::read($draft, PostingValidationPolicy::default()->withPdfTeleworkRules())->result()),
        );
    }

    public function testPolicyViolationIsNotRepeatedAtAReportedPath(): void
    {
        $draft = PostingDrafts::request(self::teleworking(new TeleworkDraft(true, null, 'italie', 2)));

        $reading = PostingRequestReader::read($draft, PostingValidationPolicy::default()->withPdfTeleworkRules());

        static::assertSame(
            ['declaration.posting.telework.country country.unknown', 'declaration.posting.telework.workerAgreement field.required'],
            ViolationSummary::of($reading->result()),
        );
    }

    public function testViolationsNeverCarryTheInput(): void
    {
        $draft = new PostingRequestDraft(new DeclarantDraft('Nom exemple', 'secret-mail', '12345', null), PostingDrafts::declaration(PostingDrafts::details()));
        $worker = PostingDrafts::worker();

        $result = PostingRequestReader::read($draft, PostingValidationPolicy::default())->result();

        static::assertCount(2, $result->all());
        foreach ($result->all() as $violation) {
            $exported = $violation->message() . json_encode($violation->params(), JSON_THROW_ON_ERROR);
            static::assertStringNotContainsString('secret-mail', $exported);
            static::assertStringNotContainsString('12345', $exported);
            static::assertStringNotContainsString((string) $worker->nir, $exported);
        }
    }

    private static function teleworking(TeleworkDraft $telework): PostingDetailsDraft
    {
        return new PostingDetailsDraft(null, null, null, $telework, null, null, null, null, null, [], null);
    }
}

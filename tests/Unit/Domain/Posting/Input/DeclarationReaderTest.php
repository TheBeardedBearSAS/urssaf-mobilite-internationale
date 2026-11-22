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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyReferenceDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarationReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\SignatureDraft;

final class DeclarationReaderTest extends TestCase
{
    public function testCompleteDeclaration(): void
    {
        $reader = new DraftReader();

        $declaration = (new DeclarationReader($reader))->required('declaration', PostingDrafts::declaration(PostingDrafts::emptyDetails()));

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($declaration);
        static::assertSame('Entreprise exemple', $declaration->declaringCompany()->name()->value());
        static::assertSame('404833048', $declaration->companyReference()->siren()->value());
        static::assertSame('00022', $declaration->companyReference()->nic()->value());
        static::assertSame('000000000000000000', $declaration->companyReference()->externalAccountNumber()?->value());
        static::assertTrue($declaration->signature()->personalDataAuthorisation());
        static::assertTrue($declaration->signature()->declarationOnHonour());
        static::assertNull($declaration->posting()->worker());
    }

    public function testMissingParts(): void
    {
        $reader = new DraftReader();
        $declarations = new DeclarationReader($reader);

        static::assertNull($declarations->required('declaration', null));
        static::assertNull($declarations->required('d', new DeclarationDraft(null, null, null, null)));
        static::assertSame(
            [
                'declaration field.required',
                'd.declaringCompany field.required',
                'd.companyReference field.required',
                'd.signature field.required',
                'd.posting field.required',
            ],
            ViolationSummary::of($reader->result()),
        );
    }

    /**
     * @return iterable<string, array{DeclarationDraft, list<string>}>
     */
    public static function provideSingleMissingPart(): iterable
    {
        $company = PostingDrafts::company();
        $reference = new CompanyReferenceDraft('404833048', '', null);
        $signature = new SignatureDraft(false, false);
        $details = PostingDrafts::emptyDetails();
        yield 'declaringCompany' => [new DeclarationDraft(null, $reference, $signature, $details), ['declaringCompany field.required']];
        yield 'companyReference' => [new DeclarationDraft($company, null, $signature, $details), ['companyReference field.required']];
        yield 'signature' => [new DeclarationDraft($company, $reference, null, $details), ['signature field.required']];
        yield 'posting' => [new DeclarationDraft($company, $reference, $signature, null), ['posting field.required']];
        yield 'siren' => [new DeclarationDraft($company, new CompanyReferenceDraft(null, '', null), $signature, $details), ['companyReference.siren field.required']];
        yield 'nic' => [new DeclarationDraft($company, new CompanyReferenceDraft('404833048', null, null), $signature, $details), ['companyReference.nic field.required']];
        yield 'personalDataAuthorisation' => [
            new DeclarationDraft($company, $reference, new SignatureDraft(null, true), $details),
            ['signature.personalDataAuthorisation field.required'],
        ];
        yield 'declarationOnHonour' => [
            new DeclarationDraft($company, $reference, new SignatureDraft(true, null), $details),
            ['signature.declarationOnHonour field.required'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideSingleMissingPart')]
    public function testSingleMissingPart(DeclarationDraft $draft, array $expected): void
    {
        $reader = new DraftReader();

        static::assertNull((new DeclarationReader($reader))->required('declaration', $draft));
        static::assertSame(array_map(static fn(string $line): string => 'declaration.' . $line, $expected), ViolationSummary::of($reader->result()));
    }

    public function testInvalidReference(): void
    {
        $reader = new DraftReader();
        $draft = new DeclarationDraft(PostingDrafts::company(), new CompanyReferenceDraft('40483304a', '000222', '1'), new SignatureDraft(false, false), PostingDrafts::emptyDetails());

        static::assertNull((new DeclarationReader($reader))->required('declaration', $draft));
        static::assertSame(
            [
                'declaration.companyReference.siren siren.invalid_format',
                'declaration.companyReference.nic nic.too_long',
                'declaration.companyReference.externalAccountNumber external_account_number.invalid_format',
            ],
            ViolationSummary::of($reader->result()),
        );
    }

    public function testMinimalReferenceAndFalseSignature(): void
    {
        $reader = new DraftReader();
        $draft = new DeclarationDraft(PostingDrafts::company(), new CompanyReferenceDraft('404833048', '', null), new SignatureDraft(false, false), PostingDrafts::emptyDetails());

        $declaration = (new DeclarationReader($reader))->required('declaration', $draft);

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($declaration);
        static::assertSame('', $declaration->companyReference()->nic()->value());
        static::assertNull($declaration->companyReference()->externalAccountNumber());
        static::assertFalse($declaration->signature()->personalDataAuthorisation());
        static::assertFalse($declaration->signature()->declarationOnHonour());
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\AddressDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;

final class CompanyReaderTest extends TestCase
{
    public function testCompleteCompany(): void
    {
        $reader = new DraftReader();

        $company = (new CompanyReader($reader))->required('company', PostingDrafts::company());

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($company);
        static::assertSame('Entreprise exemple', $company->name()->value());
        static::assertTrue($company->addressKnown());
        static::assertSame(Country::Italie, $company->address()?->country());
        static::assertSame('company@example.invalid', $company->email()->value());
    }

    public function testMissingCompany(): void
    {
        $reader = new DraftReader();

        static::assertNull((new CompanyReader($reader))->required('a.company', null));
        static::assertSame(['a.company field.required'], ViolationSummary::of($reader->result()));
    }

    public function testUnknownAddressIsNotRead(): void
    {
        $reader = new DraftReader();
        $invalid = new AddressDraft(null, str_repeat('x', 256), null, null, '');

        $company = (new CompanyReader($reader))->required('company', new CompanyDraft('Entreprise exemple', false, $invalid, 'company@example.invalid'));

        static::assertTrue($reader->result()->isValid(), 'ignored by the contract: no violation');
        static::assertNotNull($company);
        static::assertFalse($company->addressKnown());
        static::assertNull($company->address());
    }

    public function testAddressIsReadWhenAddressKnownIsMissing(): void
    {
        $reader = new DraftReader();
        $invalid = new AddressDraft(null, null, null, null, 'FRANCE');

        static::assertNull((new CompanyReader($reader))->required('company', new CompanyDraft('Entreprise exemple', null, $invalid, 'company@example.invalid')));
        static::assertSame(['company.addressKnown field.required', 'company.address.line field.required'], ViolationSummary::of($reader->result()));
    }

    public function testKnownAddressStaysOptional(): void
    {
        $reader = new DraftReader();

        $company = (new CompanyReader($reader))->required('company', new CompanyDraft('', true, null, 'company@example.invalid'));

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($company);
        static::assertTrue($company->addressKnown());
        static::assertNull($company->address());
    }

    /**
     * @return iterable<string, array{CompanyDraft, string}>
     */
    public static function provideSingleMissingField(): iterable
    {
        yield 'name' => [new CompanyDraft(null, false, null, 'company@example.invalid'), 'name'];
        yield 'addressKnown' => [new CompanyDraft('Entreprise exemple', null, null, 'company@example.invalid'), 'addressKnown'];
        yield 'email' => [new CompanyDraft('Entreprise exemple', true, null, null), 'email'];
    }

    #[DataProvider('provideSingleMissingField')]
    public function testSingleMissingField(CompanyDraft $draft, string $field): void
    {
        $reader = new DraftReader();

        static::assertNull((new CompanyReader($reader))->required('company', $draft));
        static::assertSame(['company.' . $field . ' field.required'], ViolationSummary::of($reader->result()));
    }

    public function testInvalidValues(): void
    {
        $reader = new DraftReader();
        $draft = new CompanyDraft(str_repeat('x', 256), true, PostingDrafts::address(), 'company');

        static::assertNull((new CompanyReader($reader))->required('company', $draft));
        static::assertSame(['company.name label.too_long', 'company.email email_address.invalid_format'], ViolationSummary::of($reader->result()));
    }
}

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
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\AddressReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DraftReader;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\WorkerDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\WorkerReader;

final class WorkerReaderTest extends TestCase
{
    public function testCompleteDraft(): void
    {
        $reader = new DraftReader();

        $worker = (new WorkerReader($reader))->read('worker', PostingDrafts::worker());

        static::assertSame([], ViolationSummary::of($reader->result()));
        static::assertNotNull($worker);
        static::assertSame('0000000000000', $worker->nir()->value());
        static::assertSame('00', $worker->nirKey()?->value());
        static::assertSame('Exemple', $worker->usageName()->value());
        static::assertSame('Naissance', $worker->birthName()?->value());
        static::assertSame('Prenom', $worker->firstName()->value());
        static::assertSame('2000-01-01', $worker->birthDate()->toString());
        static::assertSame('Villexemple', $worker->birthPlace()?->value());
        static::assertSame(Country::Espagne, $worker->birthCountry());
        static::assertSame(Country::France, $worker->nationality());
        static::assertSame('1 rue Exemple', $worker->address()?->line()->value());
        static::assertSame('test@example.invalid', $worker->email()?->value());
        static::assertSame('0000000001', $worker->phone1()?->value());
        static::assertSame('0000000002', $worker->phone2()?->value());
    }

    public function testAbsentWorker(): void
    {
        $reader = new DraftReader();

        static::assertNull((new WorkerReader($reader))->read('worker', null));
        static::assertTrue($reader->result()->isValid());
    }

    public function testEmptyDraft(): void
    {
        $reader = new DraftReader();
        $draft = new WorkerDraft(null, null, null, null, null, null, null, null, null, null, null, null, null);

        static::assertNull((new WorkerReader($reader))->read('worker', $draft));
        static::assertSame(
            [
                'worker.nir field.required',
                'worker.usageName field.required',
                'worker.firstName field.required',
                'worker.birthDate field.required',
                'worker.nationality field.required',
            ],
            ViolationSummary::of($reader->result()),
        );
    }

    /**
     * @return iterable<string, array{WorkerDraft, string}>
     */
    public static function provideSingleMissingField(): iterable
    {
        $address = PostingDrafts::address();
        yield 'nir' => [new WorkerDraft(null, '00', 'Exemple', null, 'Prenom', '2000-01-01', null, null, 'FRANCE', $address, null, null, null), 'nir'];
        yield 'usageName' => [new WorkerDraft('0000000000000', null, null, null, 'Prenom', '2000-01-01', null, null, 'FRANCE', null, null, null, null), 'usageName'];
        yield 'firstName' => [new WorkerDraft('0000000000000', null, 'Exemple', null, null, '2000-01-01', null, null, 'FRANCE', null, null, null, null), 'firstName'];
        yield 'birthDate' => [new WorkerDraft('0000000000000', null, 'Exemple', null, 'Prenom', null, null, null, 'FRANCE', null, null, null, null), 'birthDate'];
        yield 'nationality' => [new WorkerDraft('0000000000000', null, 'Exemple', null, 'Prenom', '2000-01-01', null, null, null, null, null, null, null), 'nationality'];
    }

    #[DataProvider('provideSingleMissingField')]
    public function testSingleMissingField(WorkerDraft $draft, string $field): void
    {
        $reader = new DraftReader();

        static::assertNull((new WorkerReader($reader))->read('worker', $draft));
        static::assertSame(['worker.' . $field . ' field.required'], ViolationSummary::of($reader->result()));
    }

    public function testMinimalDraft(): void
    {
        $reader = new DraftReader();
        $draft = new WorkerDraft('0000000000000', null, '', null, '', '2000-01-01', null, null, 'FRANCE', null, null, null, null);

        $worker = (new WorkerReader($reader))->read('worker', $draft);

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($worker);
        static::assertSame('', $worker->usageName()->value(), 'no minimum length (ADR 0008)');
        static::assertNull($worker->address());
        static::assertNull($worker->nirKey());
    }

    public function testInvalidValuesAreAllReported(): void
    {
        $reader = new DraftReader();
        $long = str_repeat('x', 256);
        $draft = new WorkerDraft(
            '000000000000',
            '8',
            $long,
            $long,
            $long,
            '1969-02-30',
            $long,
            '',
            'FR',
            new AddressDraft($long, $long, $long, str_repeat('1', 16), 'italie'),
            'test',
            '123456',
            str_repeat('1', 31),
        );

        static::assertNull((new WorkerReader($reader))->read('person', $draft));
        static::assertSame(
            [
                'person.nir nir.invalid_format',
                'person.nirKey nir_key.invalid_format',
                'person.usageName label.too_long',
                'person.birthName label.too_long',
                'person.firstName label.too_long',
                'person.birthDate calendar_date.invalid_date',
                'person.birthPlace label.too_long',
                'person.birthCountry country.unknown',
                'person.nationality country.unknown',
                'person.address.line label.too_long',
                'person.address.complement label.too_long',
                'person.address.city label.too_long',
                'person.address.postalCode postal_code.too_long',
                'person.address.country country.unknown',
                'person.email email_address.invalid_format',
                'person.phone1 phone_number.invalid_length',
                'person.phone2 phone_number.invalid_length',
            ],
            ViolationSummary::of($reader->result()),
        );
        foreach ($reader->result()->all() as $violation) {
            static::assertStringNotContainsString('xxx', $violation->message());
            static::assertStringNotContainsString('000000000000', $violation->message());
            static::assertNotContains('000000000000', $violation->params());
        }
    }

    public function testAddressRequiredParts(): void
    {
        $reader = new DraftReader();
        $addresses = new AddressReader($reader);

        static::assertNull($addresses->read('a', new AddressDraft(null, null, null, null, null)));
        static::assertNull($addresses->read('b', new AddressDraft(null, null, null, null, 'FRANCE')));
        static::assertNull($addresses->read('c', new AddressDraft('1 rue Exemple', null, null, null, null)));
        static::assertSame(
            ['a.line field.required', 'a.country field.required', 'b.line field.required', 'c.country field.required'],
            ViolationSummary::of($reader->result()),
        );
    }

    public function testCompleteAddress(): void
    {
        $reader = new DraftReader();

        $address = (new AddressReader($reader))->read('a', new AddressDraft('1 rue Exemple', 'Batiment A', 'Villexemple', '00000', 'ITALIE'));

        static::assertTrue($reader->result()->isValid());
        static::assertNotNull($address);
        static::assertSame('1 rue Exemple', $address->line()->value());
        static::assertSame('Batiment A', $address->complement()?->value());
        static::assertSame('Villexemple', $address->city()?->value());
        static::assertSame('00000', $address->postalCode()?->value());
        static::assertSame(Country::Italie, $address->country());
    }

    public function testDraftIsMasked(): void
    {
        $draft = PostingDrafts::worker();

        static::assertSame(array_fill_keys(array_keys(get_object_vars($draft)), '***'), $draft->__debugInfo());
        static::assertStringNotContainsString('0000000000000', print_r($draft, true));
    }
}

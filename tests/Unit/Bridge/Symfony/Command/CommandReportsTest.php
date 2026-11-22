<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake\EnrolmentSamples;

/**
 * @mago-expect lint:too-many-methods One test per exit code, per kind of message and per rendering.
 */
#[CoversClass(CommandReports::class)]
final class CommandReportsTest extends TestCase
{
    public function testTheExitCodeOfTheActionIsReturnedAndNothingIsPrinted(): void
    {
        $output = new BufferedOutput();

        static::assertSame(3, CommandReports::guard($output, static fn(): int => 3, null, false));
        static::assertSame('', $output->fetch());
    }

    public function testAnInvalidUsageExitsWithTwo(): void
    {
        $output = new BufferedOutput();

        $exit = CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('The --offset option is wrong.'), null, false);

        static::assertSame(2, $exit);
        static::assertSame("The --offset option is wrong.\n", $output->fetch());
    }

    public function testAPackageFailureExitsWithOne(): void
    {
        $output = new BufferedOutput();

        $exit = CommandReports::guard($output, static fn(): int => throw UrssafUnavailable::dueToTransportError(Operation::ListEnrolments, RuntimeException::class), null, false);

        static::assertSame(1, $exit);
        static::assertStringContainsString('get-enrolement-list', $output->fetch());
    }

    public function testOtherFailuresAreNotHidden(): void
    {
        $this->expectException(RuntimeException::class);

        CommandReports::guard(new BufferedOutput(), static fn(): int => throw new RuntimeException('a bug'), null, false);
    }

    public function testAMessageIsNeverInterpretedAsFormatting(): void
    {
        $output = new BufferedOutput();
        $output->setDecorated(true);

        CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('<fg=red>injected</>'), null, false);

        static::assertStringContainsString('<fg=red>injected</>', $output->fetch());
    }

    public function testAViolationIsTranslatedWithItsParameters(): void
    {
        $translator = self::translator(['tenant_id.too_long' => 'The tenant id must not exceed %max_length% characters, translated.']);
        $output = new BufferedOutput();

        $exit = CommandReports::guard($output, static fn(): int => throw InvalidValue::tooLong('tenant_id', 64), $translator, false);

        static::assertSame(1, $exit);
        static::assertSame("The tenant id must not exceed 64 characters, translated.\n", $output->fetch());
    }

    public function testTheViolationBehindAnInvalidUsageIsTranslatedToo(): void
    {
        $translator = self::translator(['tenant_id.empty' => 'Translated.']);
        $output = new BufferedOutput();

        $exit = CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('English.', 0, InvalidValue::empty('tenant_id')), $translator, false);

        static::assertSame(2, $exit);
        static::assertSame("Translated.\n", $output->fetch());
    }

    public function testAnUsageErrorWithAnotherCauseIsPrintedAsIs(): void
    {
        $translator = self::translator(['tenant_id.empty' => 'Translated.']);
        $output = new BufferedOutput();

        CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('English.', 0, new RuntimeException('cause')), $translator, false);

        static::assertSame("English.\n", $output->fetch());
    }

    public function testAViolationWithoutAMessageInTheCatalogueKeepsItsEnglishMessage(): void
    {
        $output = new BufferedOutput();

        CommandReports::guard($output, static fn(): int => throw InvalidValue::empty('tenant_id'), self::translator([]), false);

        static::assertSame("Invalid tenant_id: the value must not be empty.\n", $output->fetch());
    }

    public function testAFailureThatIsNotAViolationIsNeverTranslated(): void
    {
        $translator = self::translator([]);
        $output = new BufferedOutput();

        CommandReports::guard($output, static fn(): int => throw UrssafAuthenticationFailed::withStatus(401), $translator, false);

        static::assertSame("URSSAF authentication or authorisation failed (HTTP 401).\n", $output->fetch());
    }

    /**
     * @param array<string, string> $messages by violation code
     */
    private static function translator(array $messages): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'en', 'urssaf_mobilite_internationale');

        return $translator;
    }

    /**
     * @return iterable<string, array{UrssafException}>
     */
    public static function failuresWithErrors(): iterable
    {
        $errors = ApiErrors::of(ApiError::of('E1', 'a message', 'a description'));

        yield 'rejected request' => [UrssafRequestRejected::withErrors(Operation::SubmitPosting, 400, $errors)];
        yield 'not found' => [UrssafResourceNotFound::withErrors(Operation::GetEnrolment, $errors)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::GetEnrolment, 503, null, $errors)];
    }

    #[DataProvider('failuresWithErrors')]
    public function testTheUrssafErrorsAreHiddenUnlessAsked(UrssafException $failure): void
    {
        $hidden = new BufferedOutput();
        $shown = new BufferedOutput();

        CommandReports::guard($hidden, static fn(): int => throw $failure, null, false);
        CommandReports::guard($shown, static fn(): int => throw $failure, null, true);

        static::assertStringNotContainsString('a description', $hidden->fetch());
        static::assertStringContainsString('| E1          | a message      | a description      |', $shown->fetch());
    }

    public function testAFailureWithoutUrssafErrorsSaysSo(): void
    {
        $output = new BufferedOutput();

        CommandReports::guard($output, static fn(): int => throw OutcomeUnknown::afterStatus(Operation::RequestEnrolment, 500), null, true);

        static::assertStringContainsString('URSSAF returned no error detail.', $output->fetch());
    }

    public function testAnEmptyErrorListSaysSo(): void
    {
        $output = new BufferedOutput();

        CommandReports::guard($output, static fn(): int => throw UrssafRequestRejected::withErrors(Operation::GetEnrolment, 400, ApiErrors::none()), null, true);

        $display = $output->fetch();
        static::assertStringContainsString('URSSAF returned no error detail.', $display);
        static::assertStringNotContainsString('URSSAF code', $display, 'No empty table is printed.');
    }

    public function testTheFieldsOfAnErrorAreEscapedAndAbsentOnesAreDashes(): void
    {
        $output = new BufferedOutput();

        CommandReports::renderApiErrors($output, ApiErrors::of(ApiError::of(null, '<info>m</info>', '')));

        $display = $output->fetch();
        static::assertStringContainsString('| -           | <info>m</info> | -                  |', $display);
    }

    public function testAnEnrolmentWithNoFieldIsADashPerField(): void
    {
        $output = new BufferedOutput();
        $enrolment = Enrolment::of(EnrolmentSamples::enrolmentId(), null, null, null, null, null, null);

        CommandReports::renderEnrolment($output, $enrolment);

        static::assertStringContainsString('| siren        | -', $output->fetch());
    }

    public function testTheNextPageIsAnnouncedOnlyWhenThereIsOne(): void
    {
        $enrolment = EnrolmentSamples::enrolment('ENCOURS');
        $full = new BufferedOutput();
        $last = new BufferedOutput();

        CommandReports::renderEnrolmentPage($full, EnrolmentPage::of([$enrolment, $enrolment], 4, 2));
        CommandReports::renderEnrolmentPage($last, EnrolmentPage::of([$enrolment], 4, 2));

        static::assertStringContainsString("Next page: --offset=6\n", $full->fetch());
        $display = $last->fetch();
        static::assertStringContainsString("Last page.\n", $display);
        static::assertStringNotContainsString('Next page', $display);
    }

    public function testTheDatesAreIso8601(): void
    {
        $output = new BufferedOutput();
        $enrolment = Enrolment::of(
            EnrolmentSamples::enrolmentId(),
            null,
            null,
            null,
            null,
            Instant::fromIso8601('2026-01-02T03:04:05Z'),
            Instant::fromIso8601('2026-02-03T04:05:06Z'),
        );

        CommandReports::renderEnrolment($output, $enrolment);

        $display = $output->fetch();
        static::assertStringContainsString('2026-01-02T03:04:05Z', $display);
        static::assertStringContainsString('2026-02-03T04:05:06Z', $display);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\DomainException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

/**
 * Execution guard and output of the console commands. The output never contains the personal data of a posting, the
 * content of a certificate, a token nor a secret (ADR 0006); the URSSAF error fields are printed only on request.
 */
final class CommandReports
{
    /**
     * Runs a command body: a package failure is printed and exits with 1 ({@see Command::FAILURE}), an invalid usage
     * exits with 2 ({@see Command::INVALID}), both with a message translated when a translator is available.
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     *
     * @mago-expect lint:no-boolean-flag-parameter The flag is the --show-urssaf-errors option of the command.
     */
    public static function guard(OutputInterface $output, callable $action, ?TranslatorInterface $translator, bool $showUrssafErrors): int
    {
        try {
            return $action();
        } catch (InvalidOptionException $usage) {
            // A value the package refused keeps its violation as the cause: its message may be translated.
            $cause = $usage->getPrevious();
            $message = $cause instanceof UrssafException ? self::message($cause, $translator) : $usage->getMessage();
            $output->writeln(\sprintf('<error>%s</error>', OutputFormatter::escape($message)));

            return Command::INVALID;
        } catch (UrssafException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', OutputFormatter::escape(self::message($failure, $translator))));
            if ($showUrssafErrors) {
                self::renderApiErrors($output, self::apiErrorsOf($failure));
            }

            return Command::FAILURE;
        }
    }

    /**
     * Prints the errors URSSAF returned (raw fields, escaped), one row each.
     */
    public static function renderApiErrors(OutputInterface $output, ApiErrors $errors): void
    {
        if ($errors->isEmpty()) {
            $output->writeln('URSSAF returned no error detail.');

            return;
        }
        $table = new Table($output);
        $table->setHeaders(['URSSAF code', 'URSSAF message', 'URSSAF description']);
        $table->setRows(array_map(
            static fn(ApiError $error): array => [self::cell($error->code()), self::cell($error->message()), self::cell($error->description())],
            $errors->all(),
        ));
        $table->render();
    }

    /**
     * One enrolment as a two-column table (the SIREN and NIC are public registry identifiers, ADR 0006).
     */
    public static function renderEnrolment(OutputInterface $output, Enrolment $enrolment): void
    {
        $table = new Table($output);
        $table->setHeaders(['Field', 'Value']);
        $table->setRows([
            ['id', $enrolment->id()->value()],
            ['siren', self::cell($enrolment->siren()?->value())],
            ['nic', self::cell($enrolment->nic()?->value())],
            ['category', self::cell($enrolment->rawCategory())],
            ['status', self::cell($enrolment->rawStatus())],
            ['requested at', self::instant($enrolment->requestedAt())],
            ['updated at', self::instant($enrolment->updatedAt())],
        ]);
        $table->render();
    }

    /**
     * A page of enrolments as a table, with the offset of the next page when there is one.
     */
    public static function renderEnrolmentPage(OutputInterface $output, EnrolmentPage $page): void
    {
        $table = new Table($output);
        $table->setHeaders(['Id', 'SIREN', 'NIC', 'Category', 'Status', 'Requested at', 'Updated at']);
        $table->setRows(array_map(
            static fn(Enrolment $enrolment): array => [
                $enrolment->id()->value(),
                self::cell($enrolment->siren()?->value()),
                self::cell($enrolment->nic()?->value()),
                self::cell($enrolment->rawCategory()),
                self::cell($enrolment->rawStatus()),
                self::instant($enrolment->requestedAt()),
                self::instant($enrolment->updatedAt()),
            ],
            $page->enrolments(),
        ));
        $table->render();
        $next = $page->nextOffset();
        $output->writeln($next === null ? 'Last page.' : \sprintf('Next page: --offset=%d', $next));
    }

    /**
     * The message of a failure: the translated one for a package violation that has a message in the catalogue, the
     * English one of the exception otherwise.
     */
    private static function message(UrssafException $failure, ?TranslatorInterface $translator): string
    {
        if (!$translator instanceof TranslatorInterface || !$failure instanceof DomainException) {
            return $failure->getMessage();
        }
        $parameters = [];
        foreach ($failure->params() as $name => $value) {
            $parameters['%' . $name . '%'] = $value;
        }
        $translated = $translator->trans($failure->violationCode(), $parameters, UrssafMobiliteInternationaleBundle::ALIAS);

        return $translated === $failure->violationCode() ? $failure->getMessage() : $translated;
    }

    private static function apiErrorsOf(UrssafException $failure): ApiErrors
    {
        return match (true) {
            $failure instanceof UrssafRequestRejected, $failure instanceof UrssafResourceNotFound, $failure instanceof UrssafUnavailable => $failure->errors(),
            default => ApiErrors::none(),
        };
    }

    private static function instant(?Instant $instant): string
    {
        return self::cell($instant?->toIso8601());
    }

    /**
     * A value of the table: escaped, and "-" when absent.
     */
    private static function cell(?string $value): string
    {
        return $value === null || $value === '' ? '-' : OutputFormatter::escape($value);
    }
}

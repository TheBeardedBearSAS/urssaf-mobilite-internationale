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

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;

/**
 * Deletes an enrolment, after a confirmation: without a terminal the confirmation is impossible and --force is
 * required (exit code 2 otherwise). After an uncertain failure, confirm the deletion with urssaf:mi:enrolment:show.
 */
#[AsCommand(name: 'urssaf:mi:enrolment:delete', description: 'Deletes an enrolment of the URSSAF international mobility API')]
final class DeleteEnrolmentCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly DeleteEnrolmentHandler $handler,
        private readonly array $configuredTenants,
        private readonly ?TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Identifier (UUID) of the enrolment');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addForce($this, 'Delete without asking for a confirmation (required when the command is not interactive)');
        CommandOptions::addShowUrssafErrors($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandOptions::tenantOrSingleConfigured($input, $this->configuredTenants);
            $id = CommandOptions::parsed(static fn(): EnrolmentId => EnrolmentId::fromString(CommandOptions::argument($input, 'id')));
            if (!CommandOptions::force($input) && !$this->confirmed($input, $output, $id)) {
                $output->writeln('Nothing deleted.');

                return Command::FAILURE;
            }

            ($this->handler)(new DeleteEnrolment($tenant, $id));

            $output->writeln(\sprintf('<info>Enrolment %s deleted.</info>', $id->value()));

            return Command::SUCCESS;
        }, $this->translator, CommandOptions::showUrssafErrors($input));
    }

    /**
     * @throws InvalidOptionException without a terminal to ask on
     */
    private function confirmed(InputInterface $input, OutputInterface $output, EnrolmentId $id): bool
    {
        if (!$input->isInteractive()) {
            throw new InvalidOptionException('The deletion needs a confirmation: run the command interactively or use --force.');
        }

        // @mago-expect analysis:mixed-assignment The question helper is untyped: the answer of a confirmation is a bool.
        $answer = (new QuestionHelper())->ask($input, $output, new ConfirmationQuestion(\sprintf('Delete the enrolment %s? [y/N] ', $id->value()), false));

        return $answer === true;
    }
}

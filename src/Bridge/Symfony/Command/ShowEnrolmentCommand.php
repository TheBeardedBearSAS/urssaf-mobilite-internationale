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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;

/**
 * Reads one enrolment: its status tells whether a posting may be declared for the company (ACCEPTE).
 */
#[AsCommand(name: 'urssaf:mi:enrolment:show', description: 'Shows one enrolment of the URSSAF international mobility API')]
final class ShowEnrolmentCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly GetEnrolmentHandler $handler,
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
        CommandOptions::addShowUrssafErrors($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandOptions::tenantOrSingleConfigured($input, $this->configuredTenants);
            $id = CommandOptions::parsed(static fn(): EnrolmentId => EnrolmentId::fromString(CommandOptions::argument($input, 'id')));

            CommandReports::renderEnrolment($output, ($this->handler)(new GetEnrolment($tenant, $id)));

            return Command::SUCCESS;
        }, $this->translator, CommandOptions::showUrssafErrors($input));
    }
}

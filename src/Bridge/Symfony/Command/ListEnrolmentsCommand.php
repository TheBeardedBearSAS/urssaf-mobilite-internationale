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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolments;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;

/**
 * Lists a page of the enrolments of the tenant. The defaults (offset 0, limit 20) are a package choice, not a server
 * rule (ADR 0009).
 */
#[AsCommand(name: 'urssaf:mi:enrolment:list', description: 'Lists the enrolments of the URSSAF international mobility API')]
final class ListEnrolmentsCommand extends Command
{
    private const OPTION_OFFSET = 'offset';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_SORT = 'sort';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ListEnrolmentsHandler $handler,
        private readonly array $configuredTenants,
        private readonly ?TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(self::OPTION_OFFSET, null, InputOption::VALUE_REQUIRED, 'Index of the first enrolment', (string) ListEnrolments::DEFAULT_OFFSET);
        $this->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Maximum number of enrolments (at least 1)', (string) ListEnrolments::DEFAULT_LIMIT);
        $this->addOption(self::OPTION_SORT, null, InputOption::VALUE_REQUIRED, 'Sort expression, sent as is (its syntax is not published)');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addShowUrssafErrors($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandOptions::tenantOrSingleConfigured($input, $this->configuredTenants);
            $offset = CommandOptions::integer($input, self::OPTION_OFFSET, ListEnrolments::DEFAULT_OFFSET);
            $limit = CommandOptions::integer($input, self::OPTION_LIMIT, ListEnrolments::DEFAULT_LIMIT);
            $sort = CommandOptions::text($input, self::OPTION_SORT);
            $query = CommandOptions::parsed(static fn(): ListEnrolments => new ListEnrolments($tenant, $offset, $limit, $sort));

            CommandReports::renderEnrolmentPage($output, ($this->handler)($query));

            return Command::SUCCESS;
        }, $this->translator, CommandOptions::showUrssafErrors($input));
    }
}

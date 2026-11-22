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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * Checks the installation (configuration, credentials, OAuth2 authentication, token store) and exits with 1 when a
 * check fails: suitable as a deployment step. Nothing secret is printed.
 */
#[AsCommand(name: 'urssaf:mi:check', description: 'Checks the URSSAF international mobility integration: configuration, credentials, authentication, token store')]
final class CheckInstallationCommand extends Command
{
    private const OPTION_SKIP_REMOTE = 'skip-remote';

    /**
     * @param iterable<InstallationCheck> $checks
     * @param list<string>                $configuredTenants
     */
    public function __construct(
        private readonly iterable $checks,
        private readonly array $configuredTenants,
        private readonly ?TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant to check (default: the configured tenants)');
        $this->addOption(self::OPTION_SKIP_REMOTE, null, InputOption::VALUE_NONE, 'Do not call URSSAF (no authentication check)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenants = CommandOptions::tenantsOrConfigured($input, $this->configuredTenants);
            $context = $input->getOption(self::OPTION_SKIP_REMOTE) === true ? CheckContext::withoutRemoteCalls($tenants) : CheckContext::withRemoteCalls($tenants);
            $results = [];
            foreach ($this->checks as $check) {
                array_push($results, ...$check->run($context));
            }
            $table = new Table($output);
            $table->setHeaders(['Check', 'Subject', 'Status', 'Detail']);
            $table->setRows(array_map(
                static fn(CheckResult $result): array => [$result->check, $result->subject, $result->status->value, $result->detail],
                $results,
            ));
            $table->render();
            $failures = \count(array_filter($results, static fn(CheckResult $result): bool => $result->status === CheckStatus::Failure));
            if ($failures > 0) {
                $output->writeln(\sprintf('<error>%d check(s) failed.</error>', $failures));

                return Command::FAILURE;
            }
            $output->writeln('<info>The URSSAF integration is ready.</info>');

            return Command::SUCCESS;
        }, $this->translator, false);
    }
}

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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;

/**
 * Asks URSSAF to enrol a company (a human validation follows on urssaf.fr). The SIREN and the NIC are public registry
 * identifiers; no personal data is read or printed.
 */
#[AsCommand(name: 'urssaf:mi:enrolment:request', description: 'Requests the enrolment of a company with the URSSAF international mobility API')]
final class RequestEnrolmentCommand extends Command
{
    private const OPTION_NIC = 'nic';
    private const OPTION_CATEGORY = 'category';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly RequestEnrolmentHandler $handler,
        private readonly array $configuredTenants,
        private readonly ?TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('siren', InputArgument::REQUIRED, 'SIREN of the company to enrol (9 digits)');
        $this->addOption(self::OPTION_NIC, null, InputOption::VALUE_REQUIRED, 'NIC of the establishment (5 digits, optional)');
        $this->addOption(
            self::OPTION_CATEGORY,
            null,
            InputOption::VALUE_REQUIRED,
            'Category of the enrolment: ' . implode(', ', array_map(static fn(EnrolmentCategory $category): string => $category->value, EnrolmentCategory::cases())),
        );
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addShowUrssafErrors($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandOptions::tenantOrSingleConfigured($input, $this->configuredTenants);
            $siren = CommandOptions::parsed(static fn(): Siren => Siren::fromString(CommandOptions::argument($input, 'siren')));
            $nicText = CommandOptions::text($input, self::OPTION_NIC);
            $nic = $nicText === null ? null : CommandOptions::parsed(static fn(): Nic => Nic::fromString($nicText));
            $category = self::category(CommandOptions::text($input, self::OPTION_CATEGORY));

            $enrolment = ($this->handler)(new RequestEnrolment($tenant, $siren, $nic, $category));

            $output->writeln('<info>Enrolment requested. Keep its id: it is validated by a human on urssaf.fr, then read with urssaf:mi:enrolment:show.</info>');
            CommandReports::renderEnrolment($output, $enrolment);

            return Command::SUCCESS;
        }, $this->translator, CommandOptions::showUrssafErrors($input));
    }

    /**
     * @throws InvalidOptionException for a missing or unknown category
     */
    private static function category(?string $code): EnrolmentCategory
    {
        $category = $code === null ? null : EnrolmentCategory::fromCode($code);

        return $category ?? throw new InvalidOptionException(\sprintf(
            'The --category option is required: %s.',
            implode(', ', array_map(static fn(EnrolmentCategory $case): string => $case->value, EnrolmentCategory::cases())),
        ));
    }
}

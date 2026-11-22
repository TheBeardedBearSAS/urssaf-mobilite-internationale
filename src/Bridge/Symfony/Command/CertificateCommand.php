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
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;

/**
 * Downloads the certificate of a posting dossier into a file (never to the terminal: the PDF names a posted worker).
 *
 * Exit codes: 0 certificate written, 3 not available yet (retry later, nothing written), 1 refused or unknown dossier
 * and every failure, 2 invalid usage (ADR 0003, ADR 0005, ADR 0010).
 */
#[AsCommand(name: 'urssaf:mi:certificate', description: 'Downloads the certificate of a posting dossier into a file')]
final class CertificateCommand extends Command
{
    /** Not a failure and not a success: the certificate is not available yet. */
    public const EXIT_IN_PROGRESS = 3;

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly GetCertificateHandler $handler,
        private readonly array $configuredTenants,
        private readonly ?TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('dossierId', InputArgument::REQUIRED, 'Identifier (UUID) of the dossier, the "id" of the posting receipt');
        $this->addArgument('output-file', InputArgument::REQUIRED, 'File to write the PDF to (created with the mode 0600)');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addForce($this, 'Replace the output file when it already exists');
        CommandOptions::addShowUrssafErrors($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandOptions::tenantOrSingleConfigured($input, $this->configuredTenants);
            $dossier = CommandOptions::parsed(static fn(): DossierId => DossierId::fromString(CommandOptions::argument($input, 'dossierId')));

            return $this->report($input, $output, ($this->handler)(new GetCertificate($tenant, $dossier)));
        }, $this->translator, CommandOptions::showUrssafErrors($input));
    }

    private function report(InputInterface $input, OutputInterface $output, CertificateResult $result): int
    {
        if ($result instanceof CertificateAvailable) {
            $bytes = CertificateFile::write($result->file()->stream(), CommandOptions::argument($input, 'output-file'), CommandOptions::force($input));
            $output->writeln(\sprintf('<info>Certificate written (%d bytes).</info>', $bytes));

            return Command::SUCCESS;
        }
        if ($result instanceof CertificateInProgress) {
            $output->writeln(\sprintf('The certificate is not available yet (HTTP %d): try again later.', $result->httpStatus()));
            if (CommandOptions::showUrssafErrors($input)) {
                CommandReports::renderApiErrors($output, $result->errors());
            }

            return self::EXIT_IN_PROGRESS;
        }
        \assert($result instanceof CertificateRefusedOrUnknown, 'A certificate result is available, in progress, or refused or unknown.');
        $output->writeln(\sprintf('<error>The dossier is unknown or its file was refused (HTTP %d).</error>', $result->httpStatus()));
        if (CommandOptions::showUrssafErrors($input)) {
            CommandReports::renderApiErrors($output, $result->errors());
        }

        return Command::FAILURE;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CertificateCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\DeleteEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\ListEnrolmentsCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\RequestEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\ShowEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;

/**
 * Driving adapters: the console commands (namespace "urssaf:mi:"). There is no posting command: the request holds
 * personal data that must not travel through the command line (ADR 0010, decision 4).
 */
final readonly class EntryPointRegistrar
{
    private const COMMANDS = [
        RequestEnrolmentCommand::class,
        ListEnrolmentsCommand::class,
        ShowEnrolmentCommand::class,
        DeleteEnrolmentCommand::class,
        CertificateCommand::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    public function registerConsole(): void
    {
        foreach (self::COMMANDS as $command) {
            $this->services->set($command)
                ->autowire()
                ->arg('$configuredTenants', '%' . UrssafMobiliteInternationaleBundle::PARAMETER_TENANTS . '%')
                ->arg('$translator', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE))
                ->tag('console.command');
        }
    }
}

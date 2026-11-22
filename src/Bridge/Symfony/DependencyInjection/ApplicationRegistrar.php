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

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPostingHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;

/**
 * Application layer: the use case handlers, the only public services (the entry points of the host application).
 */
final readonly class ApplicationRegistrar
{
    /** Use case handlers exposed to the host application. */
    private const HANDLERS = [
        RequestEnrolmentHandler::class,
        ListEnrolmentsHandler::class,
        GetEnrolmentHandler::class,
        DeleteEnrolmentHandler::class,
        SubmitPostingHandler::class,
        GetCertificateHandler::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    public function register(): void
    {
        foreach (self::HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->public();
        }
    }
}

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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * The options and arguments of the console commands (--tenant, --show-urssaf-errors, --force) and their reading. A
 * value the user typed that the package refuses is an invalid usage (exit code 2), never a failure.
 *
 * @mago-expect lint:too-many-methods One small reader per kind of option or argument, shared by every command.
 */
final class CommandOptions
{
    private const TENANT = 'tenant';
    private const SHOW_URSSAF_ERRORS = 'show-urssaf-errors';
    private const FORCE = 'force';

    public static function addTenant(Command $command, string $description): void
    {
        $command->addOption(self::TENANT, null, InputOption::VALUE_REQUIRED, $description);
    }

    public static function addShowUrssafErrors(Command $command): void
    {
        $command->addOption(
            self::SHOW_URSSAF_ERRORS,
            null,
            InputOption::VALUE_NONE,
            'Also print the code, message and description URSSAF returned (they may echo submitted values)',
        );
    }

    public static function addForce(Command $command, string $description): void
    {
        $command->addOption(self::FORCE, null, InputOption::VALUE_NONE, $description);
    }

    public static function showUrssafErrors(InputInterface $input): bool
    {
        return $input->getOption(self::SHOW_URSSAF_ERRORS) === true;
    }

    public static function force(InputInterface $input): bool
    {
        return $input->getOption(self::FORCE) === true;
    }

    /**
     * Builds a value object from what the user typed.
     *
     * @template T
     *
     * @param callable(): T $factory
     * @param-immediately-invoked-callable $factory
     *
     * @return T
     *
     * @throws InvalidOptionException when the package refuses the value (the message never contains it)
     */
    public static function parsed(callable $factory): mixed
    {
        try {
            return $factory();
        } catch (InvalidValue $refused) {
            throw new InvalidOptionException($refused->getMessage(), 0, $refused);
        }
    }

    /**
     * The tenant given with --tenant, else the only configured tenant.
     *
     * @param list<string> $configured
     *
     * @throws InvalidOptionException when no tenant can be determined
     */
    public static function tenantOrSingleConfigured(InputInterface $input, array $configured): TenantId
    {
        $tenant = self::text($input, self::TENANT);
        if ($tenant !== null) {
            return self::parsed(static fn(): TenantId => TenantId::fromString($tenant));
        }
        if (\count($configured) !== 1) {
            throw new InvalidOptionException(\sprintf(
                'The --tenant option is required unless exactly one tenant is configured ("%s").',
                UrssafMobiliteInternationaleBundle::PARAMETER_TENANTS,
            ));
        }

        return self::parsed(static fn(): TenantId => TenantId::fromString($configured[0]));
    }

    /**
     * The tenant given with --tenant, else the configured tenants.
     *
     * @param list<string> $configured
     *
     * @return non-empty-list<TenantId>
     *
     * @throws InvalidOptionException when no tenant can be determined
     */
    public static function tenantsOrConfigured(InputInterface $input, array $configured): array
    {
        $tenant = self::text($input, self::TENANT);
        if ($tenant !== null) {
            return [self::parsed(static fn(): TenantId => TenantId::fromString($tenant))];
        }
        $tenants = array_map(static fn(string $id): TenantId => self::parsed(static fn(): TenantId => TenantId::fromString($id)), $configured);

        return $tenants === [] ? throw new InvalidOptionException(\sprintf('No tenant to check: use --tenant=<id> or configure "%s".', UrssafMobiliteInternationaleBundle::PARAMETER_TENANTS)) : $tenants;
    }

    /**
     * A non-negative integer option (parsed without ext-filter), the default when the option is absent.
     *
     * @throws InvalidOptionException for anything but digits
     */
    public static function integer(InputInterface $input, string $name, int $default): int
    {
        $text = self::text($input, $name);
        if ($text === null) {
            return $default;
        }
        if (preg_match('/^\d{1,9}$/D', $text) !== 1) {
            throw new InvalidOptionException(\sprintf('The --%s option must be a non-negative integer.', $name));
        }

        return (int) $text;
    }

    public static function text(InputInterface $input, string $name): ?string
    {
        // @mago-expect analysis:mixed-assignment Symfony options are untyped: the value is narrowed on the next line.
        $value = $input->getOption($name);

        return \is_string($value) ? $value : null;
    }

    /**
     * The value of a required argument.
     */
    public static function argument(InputInterface $input, string $name): string
    {
        // @mago-expect analysis:mixed-assignment Symfony arguments are untyped: a required argument is a string.
        $value = $input->getArgument($name);
        \assert(\is_string($value), 'A required argument is a string.');

        return $value;
    }
}

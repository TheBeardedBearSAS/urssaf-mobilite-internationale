<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * @mago-expect lint:too-many-methods One test per reader and per refusal.
 */
#[CoversClass(CommandOptions::class)]
final class CommandOptionsTest extends TestCase
{
    public function testTheCommonOptionsAreDeclared(): void
    {
        $command = new Command('probe');

        CommandOptions::addTenant($command, 'Tenant description');
        CommandOptions::addShowUrssafErrors($command);
        CommandOptions::addForce($command, 'Force description');

        $definition = $command->getDefinition();
        static::assertTrue($definition->getOption('tenant')->isValueRequired());
        static::assertSame('Tenant description', $definition->getOption('tenant')->getDescription());
        static::assertFalse($definition->getOption('show-urssaf-errors')->acceptValue());
        static::assertStringContainsString('may echo submitted values', $definition->getOption('show-urssaf-errors')->getDescription());
        static::assertFalse($definition->getOption('force')->acceptValue());
        static::assertSame('Force description', $definition->getOption('force')->getDescription());
    }

    public function testTheFlagsAreOffByDefaultAndOnWhenGiven(): void
    {
        static::assertFalse(CommandOptions::showUrssafErrors(self::input([])));
        static::assertFalse(CommandOptions::force(self::input([])));
        static::assertTrue(CommandOptions::showUrssafErrors(self::input(['--show-urssaf-errors' => true])));
        static::assertTrue(CommandOptions::force(self::input(['--force' => true])));
        static::assertFalse(CommandOptions::force(self::input(['--show-urssaf-errors' => true])));
        static::assertFalse(CommandOptions::showUrssafErrors(self::input(['--force' => true])));
    }

    public function testAValueTheUserTypedIsParsedOrRefusedWithAnInvalidUsage(): void
    {
        static::assertSame('tenant-1', CommandOptions::parsed(static fn(): string => TenantId::fromString('tenant-1')->value()));

        try {
            CommandOptions::parsed(static fn(): TenantId => TenantId::fromString(''));
            static::fail('An empty tenant must be refused.');
        } catch (InvalidOptionException $refused) {
            static::assertSame('Invalid tenant_id: the value must not be empty.', $refused->getMessage());
            static::assertInstanceOf(InvalidValue::class, $refused->getPrevious());
            static::assertSame(0, $refused->getCode());
        }
    }

    public function testOtherFailuresAreNotHidden(): void
    {
        $this->expectException(RuntimeException::class);

        CommandOptions::parsed(static fn(): never => throw new RuntimeException('boom'));
    }

    public function testTheTenantOptionWinsOverTheConfiguration(): void
    {
        static::assertSame('tenant-9', CommandOptions::tenantOrSingleConfigured(self::input(['--tenant' => 'tenant-9']), ['tenant-1'])->value());
        static::assertSame('tenant-9', CommandOptions::tenantOrSingleConfigured(self::input(['--tenant' => 'tenant-9']), [])->value());
    }

    public function testTheOnlyConfiguredTenantIsTheDefault(): void
    {
        static::assertSame('tenant-1', CommandOptions::tenantOrSingleConfigured(self::input([]), ['tenant-1'])->value());
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function ambiguousTenants(): iterable
    {
        yield 'none configured' => [[]];
        yield 'several configured' => [['tenant-1', 'tenant-2']];
    }

    /**
     * @param list<string> $configured
     */
    #[DataProvider('ambiguousTenants')]
    public function testAnAmbiguousTenantIsAnInvalidUsage(array $configured): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --tenant option is required unless exactly one tenant is configured ("urssaf_mi.tenants").');

        CommandOptions::tenantOrSingleConfigured(self::input([]), $configured);
    }

    public function testAnInvalidTenantIsAnInvalidUsage(): void
    {
        $this->expectException(InvalidOptionException::class);

        CommandOptions::tenantOrSingleConfigured(self::input(['--tenant' => ' ']), []);
    }

    public function testAnInvalidConfiguredTenantIsAnInvalidUsage(): void
    {
        $this->expectException(InvalidOptionException::class);

        CommandOptions::tenantOrSingleConfigured(self::input([]), [' ']);
    }

    public function testTheTenantsToCheck(): void
    {
        static::assertSame(['tenant-9'], self::values(CommandOptions::tenantsOrConfigured(self::input(['--tenant' => 'tenant-9']), ['tenant-1'])));
        static::assertSame(['tenant-1', 'tenant-2'], self::values(CommandOptions::tenantsOrConfigured(self::input([]), ['tenant-1', 'tenant-2'])));
    }

    public function testNoTenantToCheckIsAnInvalidUsage(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('No tenant to check: use --tenant=<id> or configure "urssaf_mi.tenants".');

        CommandOptions::tenantsOrConfigured(self::input([]), []);
    }

    public function testAnInvalidTenantToCheckIsAnInvalidUsage(): void
    {
        $this->expectException(InvalidOptionException::class);

        CommandOptions::tenantsOrConfigured(self::input([]), ['tenant-1', ' ']);
    }

    public function testAnInvalidTenantOptionToCheckIsAnInvalidUsage(): void
    {
        $this->expectException(InvalidOptionException::class);

        CommandOptions::tenantsOrConfigured(self::input(['--tenant' => ' ']), ['tenant-1']);
    }

    /**
     * @return iterable<string, array{array<string, string>, int}>
     */
    public static function integers(): iterable
    {
        yield 'absent' => [[], 7];
        yield 'zero' => [['--offset' => '0'], 0];
        yield 'digits' => [['--offset' => '42'], 42];
        yield 'leading zeros' => [['--offset' => '007'], 7];
        yield 'nine digits' => [['--offset' => '123456789'], 123_456_789];
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('integers')]
    public function testANonNegativeIntegerOption(array $input, int $expected): void
    {
        static::assertSame($expected, CommandOptions::integer(self::input($input), 'offset', 7));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIntegers(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => [' -1'];
        yield 'sign' => ['+1'];
        yield 'decimal' => ['1.5'];
        yield 'text' => ['abc'];
        yield 'text after digits' => ['5a'];
        yield 'text before digits' => ['a5'];
        yield 'trailing newline' => ["5\n"];
        yield 'ten digits' => ['1234567890'];
    }

    #[DataProvider('notIntegers')]
    public function testAnIntegerOptionThatIsNotDigitsIsAnInvalidUsage(string $value): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --offset option must be a non-negative integer.');

        CommandOptions::integer(self::input(['--offset' => $value]), 'offset', 7);
    }

    public function testTextOptionsAndArguments(): void
    {
        $input = self::input(['--tenant' => 'tenant-9', 'id' => 'an-id']);

        static::assertSame('tenant-9', CommandOptions::text($input, 'tenant'));
        static::assertNull(CommandOptions::text(self::input([]), 'tenant'));
        static::assertNull(CommandOptions::text(self::input(['--force' => true]), 'force'));
        static::assertSame('an-id', CommandOptions::argument($input, 'id'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function input(array $values): InputInterface
    {
        return new ArrayInput($values, new InputDefinition([
            new InputArgument('id', InputArgument::OPTIONAL),
            new InputOption('tenant', null, InputOption::VALUE_REQUIRED),
            new InputOption('offset', null, InputOption::VALUE_REQUIRED),
            new InputOption('force', null, InputOption::VALUE_NONE),
            new InputOption('show-urssaf-errors', null, InputOption::VALUE_NONE),
        ]));
    }

    /**
     * @param list<TenantId> $tenants
     *
     * @return list<string>
     */
    private static function values(array $tenants): array
    {
        return array_map(static fn(TenantId $tenant): string => $tenant->value(), $tenants);
    }
}

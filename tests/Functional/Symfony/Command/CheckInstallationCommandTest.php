<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Output;

/**
 * "urssaf:mi:check": one method per check outcome.
 *
 * @mago-expect lint:too-many-methods
 */
final class CheckInstallationCommandTest extends TestCase
{
    private const SHARED_POOL_WARNING = 'A token can be saved, read and deleted, but the pool "cache.app" is shared with the application: declare a dedicated pool, so that clearing the application cache does not drop the tokens (docs/symfony.md).';
    private const CONFIGURATION = ['configuration', '', 'ok', 'sandbox: API https://api-edi.urssaf.fr/tiersdecl/v1/mobilite, token https://api-edi.urssaf.fr/api/oauth/v1/token'];

    public function testTheCommandIsDefinedWithItsOptions(): void
    {
        // Keep the harness alive: it owns the kernel and the PHP handler guard (lowest dependencies register handlers).
        $harness = Harness::boot();
        $command = (new Application($harness->kernel))->find('urssaf:mi:check');

        static::assertSame('urssaf:mi:check', $command->getName());
        static::assertSame('Checks the URSSAF international mobility integration: configuration, credentials, authentication, token store', $command->getDescription());
        $tenant = $command->getDefinition()->getOption('tenant');
        static::assertTrue($tenant->isValueRequired());
        static::assertSame('Tenant to check (default: the configured tenants)', $tenant->getDescription());
        $skipRemote = $command->getDefinition()->getOption('skip-remote');
        static::assertFalse($skipRemote->acceptValue());
        static::assertSame('Do not call URSSAF (no authentication check)', $skipRemote->getDescription());
    }

    public function testEveryCheckIsReportedInOrderAndHostChecksComeLast(): void
    {
        $command = self::tester(Harness::boot());

        static::assertSame(0, $command->execute([]));

        static::assertSame([
            self::CONFIGURATION,
            ['credentials', 'tenant-1', 'ok', 'Credentials found.'],
            ['authentication', 'tenant-1', 'ok', 'Access token granted by URSSAF.'],
            ['token store', '', 'warning', self::SHARED_POOL_WARNING],
            ['stub', '', 'ok', 'fine'],
        ], self::rows($command));
        static::assertMatchesRegularExpression('/^\| Check +\| Subject +\| Status +\| Detail +\|$/m', $command->getDisplay());
        static::assertStringContainsString('The URSSAF integration is ready.', $command->getDisplay());
        static::assertStringNotContainsString('failed', $command->getDisplay());
    }

    public function testTheOutputNeverContainsASecretOrAToken(): void
    {
        $command = self::tester(Harness::boot());

        $command->execute([]);

        static::assertStringNotContainsString(TestKernel::SECRET, $command->getDisplay());
        static::assertStringNotContainsString('fictitious-access-token', $command->getDisplay());
    }

    public function testTheAuthenticationIsAskedToUrssafOnEveryRunWhateverTheTokenStore(): void
    {
        $harness = Harness::boot();

        self::tester($harness)->execute([]);
        self::tester($harness)->execute([]);

        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testTheTokensOfTheApplicationAreLeftUntouched(): void
    {
        $harness = Harness::boot(['token_store' => ['cache_pool' => 'test.pool']]);

        self::tester($harness)->execute([]);

        // The probe is deleted: no token remains in the pool.
        static::assertSame([], array_filter($harness->privateService('test.pool', ArrayAdapter::class)->getValues(), static fn(mixed $value): bool => $value !== null));
    }

    public function testRefusedCredentialsFailTheCheck(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = self::tester($harness);

        static::assertSame(1, $command->execute([]));

        $rows = self::rows($command);
        static::assertSame(['credentials', 'tenant-1', 'ok', 'Credentials found.'], $rows[1]);
        static::assertSame(['authentication', 'tenant-1', 'failure'], \array_slice($rows[2], 0, 3));
        static::assertNotSame('', $rows[2][3]);
        static::assertStringNotContainsString(TestKernel::SECRET, $command->getDisplay());
        static::assertStringContainsString('1 check(s) failed.', $command->getDisplay());
        static::assertStringNotContainsString('ready', $command->getDisplay());
    }

    public function testAnUnavailableTokenEndpointFailsTheCheck(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->failTransport(HttpScript::ROUTE_AUTHENTICATION);
        $command = self::tester($harness);

        static::assertSame(1, $command->execute([]));

        $rows = self::rows($command);
        static::assertSame(['authentication', 'tenant-1', 'failure'], \array_slice($rows[2], 0, 3));
        static::assertSame('token store', $rows[3][0]);
        static::assertStringContainsString('1 check(s) failed.', $command->getDisplay());
    }

    public function testATenantWithoutCredentialsFailsWithoutCallingUrssafForIt(): void
    {
        $harness = Harness::boot();
        $command = self::tester($harness);

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-9']));

        $rows = self::rows($command);
        static::assertSame(['credentials', 'tenant-9', 'failure', 'No credentials are configured for tenant "tenant-9".'], $rows[1]);
        static::assertSame(['authentication', 'tenant-9', 'failure', 'No credentials are configured for tenant "tenant-9".'], $rows[2]);
        static::assertStringContainsString('2 check(s) failed.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAPackageFailureRaisedByAHostCheckIsPrintedAsAnError(): void
    {
        $command = self::tester(Harness::boot());

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-throws', '--skip-remote' => true]));

        static::assertStringContainsString('The host check is broken.', $command->getDisplay());
        static::assertStringNotContainsString('URSSAF returned no error detail.', $command->getDisplay(), 'The check command never prints URSSAF errors.');
        static::assertSame([], self::rows($command));
    }

    public function testSkipRemoteDoesNotCallUrssaf(): void
    {
        $harness = Harness::boot();
        $command = self::tester($harness);

        static::assertSame(0, $command->execute(['--skip-remote' => true]));

        static::assertSame(['authentication', '', 'skipped', 'Not checked (--skip-remote).'], self::rows($command)[2]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testNoTenantToCheckIsAnInvalidUsage(): void
    {
        $command = self::tester(Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials_provider']));

        static::assertSame(2, $command->execute([]));

        static::assertStringContainsString('No tenant to check: use --tenant=<id> or configure "urssaf_mi.tenants".', $command->getDisplay());
        static::assertSame([], self::rows($command));
    }

    public function testTheTenantsOfTheConfigurationAreChecked(): void
    {
        $config = ['credentials' => null, 'credentials_provider' => 'test.credentials_provider', 'tenants' => ['tenant-1']];
        $command = self::tester(Harness::boot($config));

        static::assertSame(0, $command->execute(['--skip-remote' => true]));

        static::assertSame(['credentials', 'tenant-1', 'ok', 'Credentials found.'], self::rows($command)[1]);
    }

    public function testAnInvalidTenantIsAnInvalidUsage(): void
    {
        $harness = Harness::boot();
        $command = self::tester($harness);

        static::assertSame(2, $command->execute(['--tenant' => ' ']));

        static::assertStringContainsString('The tenant id is required.', $command->getDisplay());
        static::assertSame([], self::rows($command));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAnInvalidConfigurationIsAFailedCheckNotAStackTrace(): void
    {
        $_ENV['URSSAF_TEST_BASE_URI'] = 'http://mock.example.test';
        try {
            $harness = Harness::boot(['base_uri' => '%env(URSSAF_TEST_BASE_URI)%']);
            $command = self::tester($harness);

            static::assertSame(1, $command->execute([]));

            $rows = self::rows($command);
            static::assertSame(['configuration', '', 'failure', 'Invalid base_uri: the URI must use the https scheme.'], $rows[0]);
            static::assertSame(['credentials', 'tenant-1', 'ok', 'Credentials found.'], $rows[1]);
            static::assertSame(['authentication', '', 'failure', 'Invalid base_uri: the URI must use the https scheme.'], $rows[2]);
            static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        } finally {
            unset($_ENV['URSSAF_TEST_BASE_URI']);
        }
    }

    public function testAnUnknownEnvironmentIsAFailedCheckNotAStackTrace(): void
    {
        $_ENV['URSSAF_TEST_ENV'] = 'staging';
        try {
            $command = self::tester(Harness::boot(['environment' => '%env(URSSAF_TEST_ENV)%']));

            static::assertSame(1, $command->execute([]));

            $rows = self::rows($command);
            static::assertSame(['configuration', '', 'failure'], \array_slice($rows[0], 0, 3));
            static::assertStringContainsString('staging', $rows[0][3]);
            static::assertSame(['authentication', '', 'failure'], \array_slice($rows[2], 0, 3));
        } finally {
            unset($_ENV['URSSAF_TEST_ENV']);
        }
    }

    public function testTheOverriddenUrisAreReported(): void
    {
        $command = self::tester(Harness::boot(['base_uri' => 'https://mock.example.test/mi', 'token_uri' => 'https://mock.example.test/token']));

        $command->execute(['--skip-remote' => true]);

        static::assertSame(
            ['configuration', '', 'ok', 'sandbox: API https://mock.example.test/mi, token https://mock.example.test/token'],
            self::rows($command)[0],
        );
    }

    public function testTheSimulatedGatewayIsReportedInsteadOfTheAuthentication(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = self::tester($harness);

        static::assertSame(0, $command->execute([]));

        static::assertSame([
            self::CONFIGURATION,
            ['credentials', 'tenant-1', 'ok', 'Credentials found.'],
            ['gateway', '', 'warning', 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.'],
            ['token store', '', 'warning', self::SHARED_POOL_WARNING],
            ['stub', '', 'ok', 'fine'],
        ], self::rows($command));
        static::assertStringContainsString('The URSSAF integration is ready.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertSame([], $harness->client->getRequests());
    }

    public function testATokenStoreServiceIsChecked(): void
    {
        $command = self::tester(Harness::boot(['token_store' => ['service' => 'test.token_store']]));

        static::assertSame(0, $command->execute(['--skip-remote' => true]));

        static::assertSame(['token store', '', 'ok', 'A token can be saved, read and deleted.'], self::rows($command)[3]);
    }

    public function testOnlyTheSharedApplicationPoolIsFlagged(): void
    {
        static::assertTrue(self::sharedPoolFlag([]));
        static::assertFalse(self::sharedPoolFlag(['token_store' => ['cache_pool' => 'test.pool']]));
        static::assertFalse(self::sharedPoolFlag(['token_store' => ['service' => 'test.token_store']]));
        static::assertFalse(self::sharedPoolFlag(['token_store' => ['cache_pool' => 'test.pool', 'service' => 'test.token_store']]));
    }

    public function testThePackageChecksRunBeforeThoseOfTheHostByDefault(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([ConfigurationCheck::class, CredentialsCheck::class, AuthenticationCheck::class, TokenStoreCheck::class] as $class) {
            static::assertSame([['priority' => 1]], $container->getDefinition($class)->getTag(InstallationCheck::TAG));
        }
        static::assertSame([[]], $container->getDefinition('test.check')->getTag(InstallationCheck::TAG));
        static::assertTrue($container->getDefinition(CheckInstallationCommand::class)->hasTag('console.command'));
    }

    public function testTheProbeTokenProviderHasATokenStoreOfItsOwnAndLogsOnTheUrssafMiChannel(): void
    {
        $custom = (new TestKernel())->definitions()->getDefinition(ServiceIds::PROBE_TOKEN_PROVIDER);
        $default = (new TestKernel(['http_client' => null]))->definitions()->getDefinition(ServiceIds::PROBE_TOKEN_PROVIDER);

        static::assertEquals(new Reference('test.http_client'), $custom->getArgument(0));
        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $default->getArgument(0));
        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $default->getArgument(1));
        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $default->getArgument(2));
        static::assertEquals(new Definition(InMemoryTokenStore::class), $default->getArgument(3));
        static::assertEquals(new Reference(Clock::class), $default->getArgument(4));
        static::assertEquals(new Reference(ServiceIds::API_CONFIGURATION), $default->getArgument(5));
        static::assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $default->getArgument(6));
        static::assertSame([['channel' => 'urssaf_mi']], $default->getTag('monolog.logger'));
    }

    public function testTheProbeTokenProviderIsNotDeclaredWithTheSimulatedGateway(): void
    {
        $container = (new TestKernel(['gateway' => ['simulated' => true]]))->definitions();

        static::assertFalse($container->hasDefinition(ServiceIds::PROBE_TOKEN_PROVIDER));
        static::assertFalse($container->hasDefinition(AuthenticationCheck::class));
        static::assertTrue($container->hasDefinition(SimulatedGatewayCheck::class));
    }

    private static function tester(Harness $harness): CommandTester
    {
        return $harness->command('urssaf:mi:check');
    }

    /**
     * The rows of the report table, header excluded.
     *
     * @return list<list<string>>
     */
    private static function rows(CommandTester $command): array
    {
        return \array_slice(Output::rows($command->getDisplay()), 1);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sharedPoolFlag(array $config): mixed
    {
        return (new TestKernel($config))->definitions()->getDefinition(TokenStoreCheck::class)->getArgument(2);
    }
}

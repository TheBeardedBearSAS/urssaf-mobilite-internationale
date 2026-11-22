<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\DeleteEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPostingHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Clock;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificateHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\ListEnrolmentsHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CertificateCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\DeleteEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\ListEnrolmentsCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\RequestEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command\ShowEnrolmentCommand;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App\TestKernel;

/**
 * The services as declared by the bundle, before the container is compiled.
 *
 * @mago-expect lint:too-many-methods One test per service or per option that changes a definition.
 */
final class ServiceDefinitionsTest extends TestCase
{
    public function testTheDefaultHttpClientAndTokenStoreSettings(): void
    {
        $container = (new TestKernel(['http_client' => null]))->definitions();

        static::assertSame([['timeout' => 15.0]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
        static::assertEquals([new Reference('cache.app')], $container->getDefinition(TokenStore::class)->getArguments());
    }

    public function testTheDefaultHttpClientIsWrappedInAPsr18ClientAndGivenToTheTransport(): void
    {
        $container = (new TestKernel(['http_client' => null]))->definitions();

        static::assertEquals([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)], $container->getDefinition(ServiceIds::PSR18_CLIENT)->getArguments());
        static::assertEquals(
            [new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT)],
            $container->getDefinition(ServiceIds::HTTP_TRANSPORT)->getArguments(),
        );
    }

    public function testTheConfiguredHttpClientSendsTheRequestsButTheFactoriesStayThoseOfTheDefaultClient(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertEquals(
            [new Reference('test.http_client'), new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT)],
            $container->getDefinition(ServiceIds::HTTP_TRANSPORT)->getArguments(),
        );
    }

    public function testTheHttpTimeoutIsConfigurable(): void
    {
        $container = (new TestKernel(['http_timeout' => 2.5]))->definitions();

        static::assertSame([['timeout' => 2.5]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
    }

    public function testTheGatewayOptionsLogOnTheUrssafMiChannelAndCarryTheOptions(): void
    {
        $container = (new TestKernel(['rate_limiter' => ['enabled' => true], 'certificate' => ['bad_request_as_in_progress' => false]]))->definitions();

        $options = $container->getDefinition(ServiceIds::GATEWAY_OPTIONS);

        static::assertSame([['channel' => 'urssaf_mi']], $options->getTag('monolog.logger'));
        static::assertEquals(new Reference(Clock::class), $options->getArgument(0));
        static::assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $options->getArgument(1));
        static::assertTrue($options->getArgument(2));
        static::assertFalse($options->getArgument(3));
        static::assertSame([], $container->getDefinition(MobilityGateway::class)->getTag('monolog.logger'));
    }

    public function testTheRateLimiterIsOffAndTheCertificateBadRequestIsInProgressByDefault(): void
    {
        $options = (new TestKernel())->definitions()->getDefinition(ServiceIds::GATEWAY_OPTIONS);

        static::assertFalse($options->getArgument(2));
        static::assertTrue($options->getArgument(3));
    }

    public function testTheCredentialsProviderServiceReplacesTheInMemoryProvider(): void
    {
        $container = (new TestKernel(['credentials' => null, 'credentials_provider' => 'test.credentials_provider']))->definitions();

        static::assertFalse($container->hasDefinition(CredentialsProvider::class));
        static::assertSame('test.credentials_provider', (string) $container->getAlias(CredentialsProvider::class));
    }

    public function testTheValidationPolicyIsBuiltFromTheConfiguration(): void
    {
        $default = (new TestKernel())->definitions()->getDefinition(PostingValidationPolicy::class);
        $strict = (new TestKernel(['validation' => ['pdf_telework_rules' => true]]))->definitions()->getDefinition(PostingValidationPolicy::class);

        static::assertSame([false], $default->getArguments());
        static::assertSame([true], $strict->getArguments());
        static::assertFalse($default->isPublic());
    }

    public function testEveryServiceIdOfTheBundleIsPrefixed(): void
    {
        $definitions = (new TestKernel())->definitions()->getDefinitions();

        foreach ([ServiceIds::SYMFONY_HTTP_CLIENT, ServiceIds::PSR18_CLIENT, ServiceIds::API_CONFIGURATION, ServiceIds::GATEWAY_OPTIONS, ServiceIds::HTTP_TRANSPORT, ServiceIds::PROBE_TOKEN_PROVIDER] as $id) {
            static::assertStringStartsWith('urssaf_mi.', $id);
            static::assertArrayHasKey($id, $definitions);
            static::assertFalse($definitions[$id]->isPublic(), $id);
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function handlers(): iterable
    {
        yield 'request enrolment' => [RequestEnrolmentHandler::class];
        yield 'list enrolments' => [ListEnrolmentsHandler::class];
        yield 'get enrolment' => [GetEnrolmentHandler::class];
        yield 'delete enrolment' => [DeleteEnrolmentHandler::class];
        yield 'submit posting' => [SubmitPostingHandler::class];
        yield 'get certificate' => [GetCertificateHandler::class];
    }

    /**
     * @param class-string $handler
     */
    #[DataProvider('handlers')]
    public function testTheUseCaseHandlersArePublicAndAutowired(string $handler): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition($handler);

        static::assertTrue($definition->isPublic());
        static::assertTrue($definition->isAutowired());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function commands(): iterable
    {
        yield 'request' => [RequestEnrolmentCommand::class];
        yield 'list' => [ListEnrolmentsCommand::class];
        yield 'show' => [ShowEnrolmentCommand::class];
        yield 'delete' => [DeleteEnrolmentCommand::class];
        yield 'certificate' => [CertificateCommand::class];
    }

    /**
     * @param class-string $command
     */
    #[DataProvider('commands')]
    public function testTheCommandsAreAutowiredTaggedAndGivenTheTenantsAndTheTranslator(string $command): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition($command);

        static::assertTrue($definition->isAutowired());
        static::assertTrue($definition->hasTag('console.command'));
        static::assertSame('%urssaf_mi.tenants%', $definition->getArgument('$configuredTenants'));
        static::assertEquals(new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument('$translator'));
    }

    public function testTheCheckCommandIsTaggedAndGivenTheTenantsAndTheTranslator(): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition(CheckInstallationCommand::class);

        static::assertTrue($definition->hasTag('console.command'));
        static::assertSame('%urssaf_mi.tenants%', $definition->getArgument(1));
        static::assertEquals(new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(2));
    }

    public function testHostChecksAreAutoconfigured(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertTrue($container->getDefinition('test.check')->hasTag('urssaf_mi.installation_check'));
    }

    public function testTheSimulatedGatewayIsAPublicAliasAndNoHttpServiceIsDeclared(): void
    {
        $container = (new TestKernel(['gateway' => ['simulated' => true]]))->definitions();

        static::assertTrue($container->getAlias(SimulatedMobilityGateway::class)->isPublic());
        static::assertSame(MobilityGateway::class, (string) $container->getAlias(SimulatedMobilityGateway::class));
        static::assertSame(['sandbox'], $container->getDefinition(MobilityGateway::class)->getArguments());
        static::assertFalse($container->hasDefinition(ServiceIds::SYMFONY_HTTP_CLIENT));
        static::assertFalse($container->hasDefinition(ServiceIds::HTTP_TRANSPORT));
        static::assertFalse($container->hasDefinition(ServiceIds::GATEWAY_OPTIONS));
    }

    public function testTheIdsOfTheBundleCannotClashWithAnotherUrssafBundle(): void
    {
        $ids = array_filter(
            array_map(strval(...), array_keys((new TestKernel())->definitions()->getDefinitions())),
            // Internal definitions of the container (".instanceof.<class>.0.<id>") are not service ids.
            static fn(string $id): bool => !str_starts_with($id, '.') && stripos($id, 'urssaf') !== false,
        );

        static::assertNotSame([], $ids);
        foreach ($ids as $id) {
            static::assertMatchesRegularExpression('/^(urssaf_mi\.|TheBeardedBear\\\\UrssafMobiliteInternationale\\\\)/', $id);
        }
    }
}

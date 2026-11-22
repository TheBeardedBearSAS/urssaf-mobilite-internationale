<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\App;

use Http\Mock\Client as MockClient;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\StubCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\ThrowingCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;

/**
 * Minimal application hosting the bundle: the framework and the bundle. The URSSAF HTTP client is a mock
 * ("test.http_client") and the clock a fixed one ("clock").
 *
 * The compiled container is cached per configuration for the duration of the PHP process only (a stale container
 * would hide a code change, and mutation testing swaps classes under a running process).
 */
final class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    // @mago-expect lint:no-literal-password Fake secret of the test application, never a real credential.
    public const SECRET = 'top-secret-client-secret';
    // Far in the future: the cache pools expire items against the real time, not the application clock.
    public const NOW = '2099-03-01 00:00:00 UTC';

    private static ?string $cacheRoot = null;

    /**
     * @param array<string, mixed> $bundleConfig configuration of "urssaf_mobilite_internationale", merged over the defaults
     */
    public function __construct(private readonly array $bundleConfig = [])
    {
        $_ENV['URSSAF_TEST_SECRET'] = self::SECRET;
        parent::__construct('t' . substr(md5(serialize($bundleConfig)), 0, 12), false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the constructor.
        return [
            'environment' => 'sandbox',
            'credentials' => ['tenant-1' => ['client_id' => 'client-1', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']],
            'http_client' => 'test.http_client',
        ];
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new UrssafMobiliteInternationaleBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 4);
    }

    public function getCacheDir(): string
    {
        if (self::$cacheRoot === null) {
            self::$cacheRoot = $this->getProjectDir() . '/var/functional/cache/' . bin2hex(random_bytes(8));
            register_shutdown_function(static function (): void {
                try {
                    (new Filesystem())->remove((string) self::$cacheRoot);
                } catch (IOException) {
                    // @mago-expect lint:no-empty-catch-clause The cache is disposable: a directory left behind is harmless.
                }
            });
        }

        return self::$cacheRoot . '/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir() . '/log';
    }

    /**
     * The services as declared by the bundle: extensions loaded, but nothing removed, inlined nor resolved.
     */
    public function definitions(): ContainerBuilder
    {
        $this->initializeBundles();
        $container = $this->buildContainer();
        $passes = $container->getCompilerPassConfig();
        $passes->setOptimizationPasses([]);
        $passes->setRemovingPasses([]);
        $passes->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        // @mago-expect lint:no-literal-password The framework "secret" of a throw-away test application.
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
        ]);
        $container->extension(UrssafMobiliteInternationaleBundle::ALIAS, array_replace_recursive(self::defaults(), $this->bundleConfig));

        $services = $container->services();
        $services->set('test.http_client', MockClient::class)->public();
        $services->set('test.pool', ArrayAdapter::class)->public();
        $services->set('test.token_store', InMemoryTokenStore::class)->public();
        $this->registerCredentialsProvider($services);
        $services->set('test.check', StubCheck::class)->autoconfigure()->public();
        $services->set('test.throwing_check', ThrowingCheck::class)->autoconfigure();
        $services->set('logger', CapturingLogger::class)->public();
        $services->set('clock', MockClock::class)->args([self::NOW])->public();
    }

    private function registerCredentialsProvider(ServicesConfigurator $services): void
    {
        $services->set('test.credentials', Credentials::class)
            ->factory([Credentials::class, 'of'])
            ->args(['provider-client', 'provider-secret']);
        $services->set('test.credentials_provider', InMemoryCredentialsProvider::class)
            ->args([['tenant-1' => new Reference('test.credentials')]])
            ->public();
    }
}

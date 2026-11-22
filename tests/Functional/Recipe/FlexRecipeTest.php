<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Recipe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Output;

/**
 * The Symfony Flex recipe stays in line with the bundle, and the endpoint generator publishes it.
 *
 * @mago-expect lint:too-many-methods One test per property of the recipe.
 */
final class FlexRecipeTest extends TestCase
{
    private const RECIPE = __DIR__ . '/../../../recipe/thebeardedbear/urssaf-mobilite-internationale/0.1';
    private const CONFIG = self::RECIPE . '/config/packages/urssaf_mobilite_internationale.yaml';
    private const VARIABLES = ['URSSAF_MI_ENV', 'URSSAF_MI_CLIENT_ID', 'URSSAF_MI_CLIENT_SECRET', 'URSSAF_MI_BASE_URI', 'URSSAF_MI_TOKEN_URI'];

    public function testTheManifestRegistersTheBundleAndCopiesTheConfiguration(): void
    {
        $manifest = self::manifest();

        static::assertSame([UrssafMobiliteInternationaleBundle::class => ['all']], $manifest['bundles']);
        static::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        static::assertIsArray($manifest['env']);
        static::assertSame(
            ['URSSAF_MI_ENV' => 'sandbox', 'URSSAF_MI_CLIENT_ID' => '', 'URSSAF_MI_CLIENT_SECRET' => '', 'URSSAF_MI_BASE_URI' => '', 'URSSAF_MI_TOKEN_URI' => ''],
            array_diff_key($manifest['env'], ['#1' => true]),
        );
    }

    public function testEveryVariableIsPrefixedSoThatOtherUrssafPackagesDoNotClash(): void
    {
        $manifest = self::manifest();
        static::assertIsArray($manifest['env']);

        foreach (array_keys(array_diff_key($manifest['env'], ['#1' => true])) as $name) {
            static::assertStringStartsWith('URSSAF_MI_', (string) $name);
        }
        // The active lines only: the comments may show the variable of another service (REDIS_DSN).
        $active = implode("\n", array_filter(explode("\n", (string) file_get_contents(self::CONFIG)), static fn(string $line): bool => !str_starts_with(ltrim($line), '#')));
        static::assertStringContainsString('%env(', $active);
        static::assertDoesNotMatchRegularExpression('/%env\((?!(default::)?URSSAF_MI_)/', $active);
    }

    public function testTheConfigurationNeverWritesTheSecret(): void
    {
        $content = (string) file_get_contents(self::CONFIG);

        static::assertStringContainsString("client_secret: '%env(URSSAF_MI_CLIENT_SECRET)%'", $content);
        static::assertSame(1, preg_match_all('/client_secret:/', $content));
    }

    public function testTheCopiedConfigurationBootsTheBundleWithEmptyUriVariables(): void
    {
        $config = self::recipeConfig();
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        self::withVariables(['URSSAF_MI_ENV' => 'sandbox', 'URSSAF_MI_CLIENT_ID' => 'client', 'URSSAF_MI_CLIENT_SECRET' => 'secret', 'URSSAF_MI_BASE_URI' => '', 'URSSAF_MI_TOKEN_URI' => ''], static function () use ($config): void {
            // Merged over the test defaults (HTTP client, a test tenant).
            $harness = Harness::boot($config['urssaf_mobilite_internationale']);
            $command = $harness->command('urssaf:mi:check');

            static::assertSame(0, $command->execute(['--skip-remote' => true]));

            $rows = Output::rows($command->getDisplay());
            static::assertContains(['configuration', '', 'ok', 'sandbox: API https://api-edi.urssaf.fr/tiersdecl/v1/mobilite, token https://api-edi.urssaf.fr/api/oauth/v1/token'], $rows);
            static::assertContains(['credentials', 'default', 'ok', 'Credentials found.'], $rows);
        });
    }

    public function testTheCopiedConfigurationBootsTheBundleWithUndefinedUriVariables(): void
    {
        $config = self::recipeConfig();
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        self::withVariables(['URSSAF_MI_ENV' => 'production', 'URSSAF_MI_CLIENT_ID' => 'client', 'URSSAF_MI_CLIENT_SECRET' => 'secret'], static function () use ($config): void {
            $command = Harness::boot($config['urssaf_mobilite_internationale'])->command('urssaf:mi:check');

            static::assertSame(0, $command->execute(['--skip-remote' => true]));

            static::assertContains(
                ['configuration', '', 'ok', 'production: API https://api.urssaf.fr/tiersdecl/v1/mobilite, token https://api.urssaf.fr/api/oauth/v1/token'],
                Output::rows($command->getDisplay()),
            );
        });
    }

    public function testTheUriVariablesPointTheBundleToAMockServer(): void
    {
        $config = self::recipeConfig();
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        $variables = ['URSSAF_MI_ENV' => 'sandbox', 'URSSAF_MI_CLIENT_ID' => 'client', 'URSSAF_MI_CLIENT_SECRET' => 'secret', 'URSSAF_MI_BASE_URI' => 'https://mock.example.test/mi', 'URSSAF_MI_TOKEN_URI' => 'https://mock.example.test/token'];
        self::withVariables($variables, static function () use ($config): void {
            $command = Harness::boot($config['urssaf_mobilite_internationale'])->command('urssaf:mi:check');

            static::assertSame(0, $command->execute(['--skip-remote' => true]));

            static::assertContains(
                ['configuration', '', 'ok', 'sandbox: API https://mock.example.test/mi, token https://mock.example.test/token'],
                Output::rows($command->getDisplay()),
            );
        });
    }

    public function testTheTestEnvironmentUsesTheSimulatedGateway(): void
    {
        $config = self::recipeConfig();
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        self::withVariables(['URSSAF_MI_ENV' => 'sandbox', 'URSSAF_MI_CLIENT_ID' => 'client', 'URSSAF_MI_CLIENT_SECRET' => 'secret'], static function () use ($config): void {
            $harness = Harness::boot([...$config['urssaf_mobilite_internationale'], ...$config['when@test']['urssaf_mobilite_internationale']]);

            static::assertTrue($harness->has(SimulatedMobilityGateway::class));
        });
    }

    public function testTheEndpointGeneratorPublishesTheRecipe(): void
    {
        $output = \dirname(__DIR__, 3) . '/var/test-flex-endpoint';
        (new Filesystem())->remove($output);
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint', $output, 'https://flex.example.com/urssaf-mi/']);

        static::assertSame(0, $process->run());

        $index = (string) file_get_contents($output . '/index.json');
        static::assertStringContainsString('"aliases": {}', $index);
        static::assertSame([
            'aliases' => [],
            'recipes' => ['thebeardedbear/urssaf-mobilite-internationale' => ['0.1']],
            'branch' => 'main',
            'is_contrib' => false,
            '_links' => [
                'repository' => 'https://flex.example.com/urssaf-mi',
                'origin_template' => '{package}:{version}@https://flex.example.com/urssaf-mi',
                'recipe_template' => 'https://flex.example.com/urssaf-mi/{package_dotted}.{version}.json',
            ],
        ], json_decode($index, true, 512, JSON_THROW_ON_ERROR));
        /** @var array{manifests: array<string, array{manifest: mixed, files: array<string, array{contents: list<string>, executable: bool}>, ref: string}>} $recipe */
        $recipe = json_decode((string) file_get_contents($output . '/thebeardedbear.urssaf-mobilite-internationale.0.1.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest = $recipe['manifests']['thebeardedbear/urssaf-mobilite-internationale'];
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $manifest['ref']);
        static::assertSame(['config/packages/urssaf_mobilite_internationale.yaml'], array_keys($manifest['files']));
        $file = $manifest['files']['config/packages/urssaf_mobilite_internationale.yaml'];
        static::assertFalse($file['executable']);
        static::assertSame((string) file_get_contents(self::CONFIG), implode("\n", $file['contents']) . "\n");
        static::assertSame(self::manifest(), $manifest['manifest']);
        (new Filesystem())->remove($output);
    }

    public function testTheGeneratorNeedsItsArguments(): void
    {
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint']);

        static::assertSame(2, $process->run());
        static::assertStringContainsString('Usage: bin/build-flex-endpoint <output-dir> <base-url>', $process->getErrorOutput());
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function manifest(): array
    {
        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertion below narrows it.
        $manifest = json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        static::assertIsArray($manifest);

        return $manifest;
    }

    /**
     * @return array{urssaf_mobilite_internationale: array<string, mixed>, 'when@test': array{urssaf_mobilite_internationale: array<string, mixed>}}
     */
    private static function recipeConfig(): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped: the assertion below narrows it.
        $config = Yaml::parseFile(self::CONFIG);
        static::assertIsArray($config);
        /** @var array{urssaf_mobilite_internationale: array<string, mixed>, 'when@test': array{urssaf_mobilite_internationale: array<string, mixed>}} $config */

        return $config;
    }

    /**
     * Runs a test with exactly these environment variables of the recipe defined.
     *
     * @param array<string, string> $variables
     * @param callable(): void      $test
     * @param-immediately-invoked-callable $test
     */
    private static function withVariables(array $variables, callable $test): void
    {
        foreach ($variables as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        try {
            $test();
        } finally {
            foreach (self::VARIABLES as $name) {
                unset($_SERVER[$name], $_ENV[$name]);
            }
        }
    }
}

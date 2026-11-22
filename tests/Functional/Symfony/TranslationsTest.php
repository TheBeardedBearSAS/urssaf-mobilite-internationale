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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;

/**
 * The shipped translations cover every violation code of the package, and only them.
 *
 * The codes are found by scanning the sources: the codes given as literals, then those composed from a subject and a
 * reason ("<subject>.<reason>"), which is how {@see \TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue}
 * builds them.
 *
 * One test per translation invariant and one scanner step per way of composing a code, hence the method count and the
 * complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TranslationsTest extends TestCase
{
    /** Locales shipped by the bundle. */
    private const LOCALES = ['en', 'fr'];

    /** Sources scanned for the codes composed from a subject: every layer that builds an InvalidValue. */
    private const COMPOSED_IN = ['src/Domain', 'src/Application', 'src/Infrastructure'];

    /**
     * Sources scanned for the codes given as literals: the domain (without the draft readers, whose literals are field
     * paths such as "declarant.name") and the application.
     */
    private const LITERAL_IN = ['src/Domain', 'src/Application'];

    private const NOT_LITERAL = 'src/Domain/Posting/Input/';

    /**
     * Files that give a variable subject to an InvalidValue factory, and the codes they raise: each one needs an entry
     * here, a new one fails the test until it is mapped.
     */
    private const DYNAMIC_SUBJECT_FILES = [
        'src/Infrastructure/Urssaf/HttpsUri.php' => ['base_uri.invalid_format', 'base_uri.not_https', 'token_uri.invalid_format', 'token_uri.not_https'],
    ];

    /** The reason of every InvalidValue factory. */
    private const SUFFIXES = [
        'empty' => 'empty',
        'invalidFormat' => 'invalid_format',
        'invalidLength' => 'invalid_length',
        'negative' => 'negative',
        'notPositive' => 'not_positive',
        'tooLong' => 'too_long',
    ];

    private const CODE = '[a-z][a-z0-9_]*';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryViolationCodeHasAMessageAndNoMessageIsAnOrphan(string $locale): void
    {
        $codes = self::codesInSources();
        $translated = array_keys(self::messages($locale));

        static::assertSame([], array_values(array_diff($codes, $translated)), 'Violation codes without a message in "' . $locale . '".');
        static::assertSame([], array_values(array_diff($translated, $codes)), 'Messages of "' . $locale . '" for a code that no source produces.');
    }

    public function testTheSourcesAreScanned(): void
    {
        static::assertGreaterThan(40, \count(self::codesInSources()));
        static::assertContains('siren.invalid_length', self::codesInSources());
        static::assertContains('calendar_date.invalid_date', self::codesInSources());
        static::assertContains('enrolment.not_accepted', self::codesInSources());
    }

    public function testTheDynamicSubjectFilesAreKnown(): void
    {
        $found = [];
        foreach (self::sources(self::COMPOSED_IN) as $path => $content) {
            if (preg_match('/InvalidValue::(?!because\b)\w+\(\s*\$/', $content) !== 1) {
                continue;
            }
            $found[] = $path;
        }
        sort($found);

        static::assertSame(array_keys(self::DYNAMIC_SUBJECT_FILES), $found);
    }

    public function testTheSuffixMapCoversEveryFactory(): void
    {
        $factories = [];
        $content = (string) file_get_contents(self::root() . '/src/Domain/Exception/InvalidValue.php');
        preg_match_all('/public static function (\w+)\(string \$subject/', $content, $factories);

        $names = array_values(array_diff($factories[1], ['because']));
        sort($names);
        $mapped = array_keys(self::SUFFIXES);
        sort($mapped);

        static::assertSame($names, $mapped);
    }

    #[DataProvider('locales')]
    public function testEveryMessageIsAFilledSentence(string $locale): void
    {
        foreach (self::messages($locale) as $code => $message) {
            static::assertMatchesRegularExpression('/^' . self::CODE . '(\.' . self::CODE . ')+$/', $code);
            static::assertMatchesRegularExpression('/^\p{Lu}.+\.$/u', $message, $code);
        }
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheKeysOfTheEnglishFileInTheSameOrder(string $locale): void
    {
        static::assertSame(array_keys(self::messages('en')), array_keys(self::messages($locale)));
    }

    #[DataProvider('locales')]
    public function testTheKeysAreSorted(string $locale): void
    {
        $keys = array_keys(self::messages($locale));
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        static::assertSame($sorted, $keys);
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheSamePlaceholdersAsTheEnglishFile(string $locale): void
    {
        $english = self::messages('en');
        foreach (self::messages($locale) as $code => $message) {
            static::assertSame(self::placeholders($english[$code]), self::placeholders($message), $code);
        }
    }

    public function testTheFrameworkTranslatorLoadsTheDomain(): void
    {
        $translator = Harness::boot()->privateService('translator', TranslatorInterface::class);
        $domain = UrssafMobiliteInternationaleBundle::ALIAS;

        static::assertSame(
            "Le SIREN doit comporter exactement 9 caractères.",
            $translator->trans('siren.invalid_length', ['%max_length%' => 9], $domain, 'fr'),
        );
        static::assertSame(
            'The tenant id must not exceed 64 characters.',
            $translator->trans('tenant_id.too_long', ['%max_length%' => 64], $domain, 'en'),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function messages(string $locale): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped; every entry is checked below.
        $parsed = Yaml::parseFile(self::root() . '/src/Bridge/Symfony/Resources/translations/' . UrssafMobiliteInternationaleBundle::ALIAS . '.' . $locale . '.yaml');
        static::assertIsArray($parsed);
        $messages = [];
        foreach ($parsed as $code => $message) { // @mago-expect analysis:mixed-assignment narrowed by the assertion below.
            static::assertIsString($message);
            $messages[(string) $code] = $message;
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $message): array
    {
        $matches = [];
        preg_match_all('/%[a-z_]+%/', $message, $matches);

        return array_values(array_map(strval(...), $matches[0]));
    }

    /**
     * Every violation code the sources can raise, sorted.
     *
     * @return list<string>
     */
    private static function codesInSources(): array
    {
        $codes = [];
        foreach (self::sources(self::LITERAL_IN) as $path => $content) {
            if (str_starts_with($path, self::NOT_LITERAL)) {
                continue;
            }
            $matches = [];
            preg_match_all("/'(" . self::CODE . '(?:\.' . self::CODE . ")+)'/", $content, $matches);
            array_push($codes, ...$matches[1]);
        }
        foreach (self::sources(self::COMPOSED_IN) as $content) {
            array_push($codes, ...self::composedCodes($content));
        }
        foreach (self::DYNAMIC_SUBJECT_FILES as $dynamic) {
            array_push($codes, ...$dynamic);
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * The codes of the InvalidValue factories called with a literal subject or with the SUBJECT constant of the class,
     * and of the "SUBJECT . '.reason'" codes.
     *
     * @return list<string>
     */
    private static function composedCodes(string $content): array
    {
        $constant = [];
        $subject = preg_match("/const SUBJECT = '(" . self::CODE . ")'/", $content, $constant) === 1 ? $constant[1] : null;
        $codes = [];
        $calls = [];
        preg_match_all("/InvalidValue::(\\w+)\\(\\s*(?:'(" . self::CODE . ")'|(self::SUBJECT))/", $content, $calls, PREG_SET_ORDER);
        foreach ($calls as $call) {
            $reason = self::SUFFIXES[$call[1]] ?? null;
            $name = ($call[2] ?? '') === '' ? $subject : $call[2];
            if ($reason !== null && $name !== null) {
                $codes[] = $name . '.' . $reason;
            }
        }
        $suffixed = [];
        preg_match_all("/self::SUBJECT\\s*\\.\\s*'\\.(" . self::CODE . ")'/", $content, $suffixed);
        foreach ($suffixed[1] as $reason) {
            Assert::assertNotNull($subject);
            $codes[] = $subject . '.' . $reason;
        }

        return $codes;
    }

    /**
     * The PHP sources of the given directories, by path relative to the package root.
     *
     * @param list<string> $directories
     *
     * @return array<string, string>
     */
    private static function sources(array $directories): array
    {
        $sources = [];
        foreach ($directories as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                Assert::assertInstanceOf(SplFileInfo::class, $file);
                if ($file->getExtension() === 'php') {
                    $sources[substr($file->getPathname(), \strlen(self::root()) + 1)] = (string) file_get_contents($file->getPathname());
                }
            }
        }
        ksort($sources);

        return $sources;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 3);
    }
}

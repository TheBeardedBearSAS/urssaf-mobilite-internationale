<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;

final class ApiConfigurationValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function insecureUris(): iterable
    {
        yield 'http' => ['http://mock.example.test', 'not_https'];
        yield 'http local host' => ['http://localhost:8080/base', 'not_https'];
        yield 'http upper case' => ['HTTP://mock.example.test', 'not_https'];
        yield 'ftp' => ['ftp://mock.example.test', 'not_https'];
        yield 'https prefix only' => ['httpsx://mock.example.test', 'not_https'];
        yield 'x https' => ['xhttps://mock.example.test', 'not_https'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedUris(): iterable
    {
        yield 'empty' => ['', 'invalid_format'];
        yield 'relative' => ['/api/base', 'invalid_format'];
        yield 'no scheme' => ['mock.example.test/base', 'invalid_format'];
        yield 'scheme relative' => ['//mock.example.test/base', 'invalid_format'];
        yield 'no host' => ['https:', 'invalid_format'];
        yield 'empty authority' => ['https:///base', 'invalid_format'];
        yield 'user info' => ['https://user@mock.example.test', 'invalid_format'];
        yield 'empty user info' => ['https://@mock.example.test', 'invalid_format'];
        yield 'user and password' => ['https://user:secret@mock.example.test', 'invalid_format'];
        yield 'password only' => ['https://:secret@mock.example.test', 'invalid_format'];
        yield 'query' => ['https://mock.example.test/base?x=1', 'invalid_format'];
        yield 'empty query' => ['https://mock.example.test/base?', 'invalid_format'];
        yield 'fragment' => ['https://mock.example.test/base#top', 'invalid_format'];
        yield 'space' => ['https://mock.example.test/ba se', 'invalid_format'];
        yield 'trailing newline' => ["https://mock.example.test/base\n", 'invalid_format'];
        yield 'leading space' => [' https://mock.example.test', 'invalid_format'];
        yield 'bad port' => ['https://mock.example.test:99999/base', 'invalid_format'];
        yield 'insecure and malformed' => ['http://user@mock.example.test', 'invalid_format'];
    }

    #[DataProvider('insecureUris')]
    #[DataProvider('malformedUris')]
    public function testAnOverriddenBaseUriMustBeAnAbsoluteHttpsUri(string $uri, string $reason): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, $uri, null, null);
            static::fail('The base URI override must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('base_uri.' . $reason, $exception->violationCode());
            static::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    #[DataProvider('insecureUris')]
    #[DataProvider('malformedUris')]
    public function testAnOverriddenTokenUriMustBeAnAbsoluteHttpsUri(string $uri, string $reason): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, null, $uri, null);
            static::fail('The token URI override must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('token_uri.' . $reason, $exception->violationCode());
        }
    }

    public function testTheNotHttpsMessageNamesTheSetting(): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, 'http://mock.example.test', null, null);
            static::fail('The base URI override must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('Invalid base_uri: the URI must use the https scheme.', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHeaderNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['X Request Id'];
        yield 'colon' => ['X-Request-Id:'];
        yield 'newline' => ["X-Request-Id\nX-Other"];
        yield 'trailing newline' => ["X-Request-Id\n"];
        yield 'non ascii' => ['X-Requête'];
    }

    #[DataProvider('invalidHeaderNames')]
    public function testTheRequestIdHeaderMustBeAHeaderName(string $name): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, null, null, $name);
            static::fail('The request id header name must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('request_id_header.invalid_format', $exception->violationCode());
        }
    }

    public function testTheSafetyMarginMustNotBeNegative(): void
    {
        try {
            new ApiConfiguration(Environment::Sandbox, null, null, null, -1);
            static::fail('A negative margin must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame('token_safety_margin.negative', $exception->violationCode());
        }
    }
}

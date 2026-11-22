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
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\UrssafEndpoints;

final class UrssafEndpointsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'token path' => ['/api/oauth/v1/token', UrssafEndpoints::TOKEN_PATH];
        yield 'token scope' => ['ods.dlagd', UrssafEndpoints::TOKEN_SCOPE];
        yield 'api base path' => ['/tiersdecl/v1/mobilite', UrssafEndpoints::API_BASE_PATH];
        yield 'enrolments' => ['/enrolements', UrssafEndpoints::ENROLMENTS_PATH];
        yield 'posting' => ['/declaration/detachement/UE', UrssafEndpoints::POSTING_PATH];
        yield 'certificate' => ['/certificat', UrssafEndpoints::CERTIFICATE_PATH];
    }

    #[DataProvider('paths')]
    public function testPathsFollowTheContract(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }
}

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

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;

final class HttpTransportTest extends TestCase
{
    public function testItExposesTheClientAndTheFactoriesItWasBuiltWith(): void
    {
        $client = new Client();
        $requests = new Psr17Factory();
        $streams = new Psr17Factory();

        $transport = new HttpTransport($client, $requests, $streams);

        static::assertSame($client, $transport->client());
        static::assertSame($requests, $transport->requestFactory());
        static::assertSame($streams, $transport->streamFactory());
    }
}

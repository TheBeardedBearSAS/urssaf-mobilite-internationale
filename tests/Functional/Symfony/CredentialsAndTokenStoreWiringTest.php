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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetEnrolmentHandler;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\Psr6TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;

final class CredentialsAndTokenStoreWiringTest extends TestCase
{
    public function testTheDefaultTokenStoreIsThePsr6StoreOfTheAppCachePool(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(Psr6TokenStore::class, $harness->privateService(TokenStore::class, TokenStore::class));
    }

    public function testTheTokenIsStoredInTheConfiguredCachePool(): void
    {
        $harness = Harness::boot(['token_store' => ['cache_pool' => 'test.pool']]);

        self::read($harness);

        static::assertCount(1, $harness->privateService('test.pool', ArrayAdapter::class)->getValues());
    }

    public function testTheTokenStoreServiceTakesPrecedenceOverTheCachePool(): void
    {
        $harness = Harness::boot(['token_store' => ['cache_pool' => 'test.pool', 'service' => 'test.token_store']]);

        self::read($harness);

        $store = $harness->privateService('test.token_store', InMemoryTokenStore::class);
        static::assertSame($store, $harness->privateService(TokenStore::class, TokenStore::class));
        static::assertCount(1, $store->keys());
        static::assertStringStartsWith('urssaf_mi.', $store->keys()[0]);
        static::assertSame([], $harness->privateService('test.pool', ArrayAdapter::class)->getValues());
    }

    public function testTheCredentialsOfTheConfigurationAreServedByAnInMemoryProvider(): void
    {
        $harness = Harness::boot();

        $provider = $harness->privateService(CredentialsProvider::class, CredentialsProvider::class);

        static::assertInstanceOf(InMemoryCredentialsProvider::class, $provider);
        static::assertSame('client-1', $provider->forTenant(TenantId::fromString('tenant-1'))->clientId());
    }

    public function testTheCredentialsProviderServiceIsUsed(): void
    {
        $harness = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials_provider']);

        static::assertSame(
            $harness->privateService('test.credentials_provider', CredentialsProvider::class),
            $harness->privateService(CredentialsProvider::class, CredentialsProvider::class),
        );
        self::read($harness);
        static::assertStringContainsString('client_id=provider-client', (string) $harness->client->getRequests()[0]->getBody());
    }

    private static function read(Harness $harness): void
    {
        $harness->get(GetEnrolmentHandler::class)(new GetEnrolment(TenantId::fromString('tenant-1'), EnrolmentId::fromString(Calls::ENROLMENT_ID)));
    }
}

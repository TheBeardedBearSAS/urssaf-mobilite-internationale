<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\TokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic\Fake\AmnesicTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic\Fake\BrokenTokenStore;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic\Fake\StickyTokenStore;

#[CoversClass(TokenStoreCheck::class)]
final class TokenStoreCheckTest extends TestCase
{
    private const NOW = '2099-03-01 00:00:00 UTC';

    public function testAWorkingStoreIsOk(): void
    {
        $results = self::probe(new InMemoryTokenStore());

        static::assertEquals([new CheckResult('token store', '', CheckStatus::Ok, 'A token can be saved, read and deleted.')], $results);
    }

    public function testTheProbeIsAShortLivedTokenUnderItsOwnKey(): void
    {
        $store = new StickyTokenStore();

        self::probe($store);

        $tokens = $store->tokens();
        static::assertSame(['urssaf_mi.check'], array_keys($tokens));
        static::assertSame('probe', $tokens['urssaf_mi.check']->value());
        static::assertEquals(new DateTimeImmutable('2099-03-01 00:01:00 UTC'), $tokens['urssaf_mi.check']->expiresAt());
    }

    public function testTheSharedApplicationPoolIsAWarning(): void
    {
        $check = new TokenStoreCheck(new InMemoryTokenStore(), new FrozenClock(new DateTimeImmutable(self::NOW)), true);

        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Warning, 'A token can be saved, read and deleted, but the pool "cache.app" is shared with the application: declare a dedicated pool, so that clearing the application cache does not drop the tokens (docs/symfony.md).')],
            $check->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')])),
        );
    }

    public function testAStoreThatForgetsTheTokenFails(): void
    {
        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Failure, 'A saved token cannot be read back: the store does not keep tokens between calls.')],
            self::probe(new AmnesicTokenStore()),
        );
    }

    public function testAStoreThatKeepsADeletedTokenFails(): void
    {
        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Failure, 'A deleted token is still readable: the store does not delete tokens.')],
            self::probe(new StickyTokenStore()),
        );
    }

    public function testAnUnavailableStoreFailsWithoutLeakingItsMessage(): void
    {
        $results = self::probe(new BrokenTokenStore());

        static::assertEquals(
            [new CheckResult('token store', '', CheckStatus::Failure, 'The token store failed (LogicException): check its configuration and availability.')],
            $results,
        );
    }

    /**
     * @return list<CheckResult>
     */
    private static function probe(TokenStore $store): array
    {
        $check = new TokenStoreCheck($store, new FrozenClock(new DateTimeImmutable(self::NOW)));

        return $check->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]));
    }
}
